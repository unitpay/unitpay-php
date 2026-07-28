<?php

namespace Unitpay\Telemetry;

use Unitpay\Exception\UnitpayValidationException;

/**
 * Builds the anonymous self-identification headers sent with every service call, and
 * holds the integration slots a merchant can fill in.
 *
 * Most Unitpay integrations are CMS modules, so "unitpay-bitrix 3.1 on Bitrix 22 under
 * PHP 8.1" is the signal worth having and the part a fixed fingerprint cannot express.
 * Every slot value is a product name and version supplied by the integrator — no PII, no
 * identifiers, no separate telemetry request.
 *
 * Mutable and shared by reference, like Api\PendingParams: services are created lazily
 * and cached by the facade, so a setCms() call made after the first payments() call still
 * has to reach the service that already exists.
 */
final class ClientInfo
{
    public const SLOT_FRAMEWORK = 'framework';
    public const SLOT_CMS = 'cms';
    public const SLOT_MODULE = 'module';

    /** Emitted in this order, narrowest context last. */
    private const SLOT_ORDER = [self::SLOT_FRAMEWORK, self::SLOT_CMS, self::SLOT_MODULE];

    /** Byte caps, generous for a real product name but enough to bound the header. */
    private const MAX_NAME_BYTES = 64;
    private const MAX_VERSION_BYTES = 32;

    private string $sdkVersion;
    private string $apiVersion;
    private bool $enabled = true;
    /**
     * The two halves are kept apart rather than joined, because a name may legitimately
     * contain the separator — a Composer package name such as "unitpay/woocommerce" would
     * make "unitpay/woocommerce/2.1" impossible to split back reliably.
     *
     * @var array<string, array{name: string, version: string}>
     */
    private array $slots = [];

    public function __construct(string $sdkVersion, string $apiVersion)
    {
        $this->sdkVersion = $sdkVersion;
        $this->apiVersion = $apiVersion;
    }

    /**
     * Fills one integration slot with a product name and version.
     *
     * A value that cannot produce a meaningful token is dropped, not rejected. These
     * setters run in an integration's bootstrap, so raising here would let a cosmetic
     * concern abort a checkout — and a CMS that stops exposing its version string is an
     * ordinary outcome of a CMS update, not an exceptional one.
     *
     * @param string $slot one of the SLOT_* constants
     * @throws UnitpayValidationException on an unknown slot. That is a programming error
     *                                    rather than merchant input: the facade passes
     *                                    only the constants, so it cannot fire from there.
     */
    public function setSlot(string $slot, string $name, string $version): void
    {
        if (!in_array($slot, self::SLOT_ORDER, true)) {
            throw new UnitpayValidationException(
                sprintf('Unknown telemetry slot "%s".', $slot)
            );
        }

        $name = self::clean($name, self::MAX_NAME_BYTES);
        $version = self::clean($version, self::MAX_VERSION_BYTES);
        if ($name === '' || $version === '') {
            // A blank half would emit a meaningless "/1.0" or "Bitrix/" token.
            return;
        }

        $this->slots[$slot] = ['name' => $name, 'version' => $version];
    }

    /**
     * Strips control characters, trims, and bounds the length.
     *
     * Removing C0 and DEL is what closes header injection at its source: trim() only cleans
     * the edges, so a CR/LF in the middle of a value used to travel all the way to the
     * transport, which joins header lines with "\r\n" on the stream path.
     *
     * The cap counts bytes because ext-mbstring is not a declared dependency. Cutting a
     * multi-byte character in half is then undone by dropping the trailing bytes of the
     * incomplete sequence — at most three — so valid UTF-8 in stays valid UTF-8 out.
     */
    private static function clean(string $value, int $maxBytes): string
    {
        $value = trim((string) preg_replace('/[\x00-\x1F\x7F]/', '', $value));
        if (strlen($value) <= $maxBytes) {
            return $value;
        }

        $value = substr($value, 0, $maxBytes);
        for ($i = 0; $i < 3 && $value !== '' && preg_match('//u', $value) !== 1; $i++) {
            $value = substr($value, 0, -1);
        }

        return $value;
    }

    /**
     * Stops sending Unitpay-Client entirely. The User-Agent keeps the SDK version alone,
     * because a request with no product identification at all is harder to support than one
     * that says which library sent it.
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * @return string[] header lines ready for the transport
     */
    public function headers(): array
    {
        if (!$this->enabled) {
            return ['User-Agent: unitpay-php-sdk/' . $this->sdkVersion];
        }

        $headers = ['User-Agent: ' . $this->userAgent()];
        $client = $this->clientJson();
        if ($client !== null) {
            $headers[] = 'Unitpay-Client: ' . $client;
        }

        return $headers;
    }

    /**
     * The only place the two halves are joined. A User-Agent cannot carry structure, so its
     * slot tokens stay the human-readable "Name/Version" form and the JSON header is what a
     * parser should read.
     */
    private function userAgent(): string
    {
        $parts = ['unitpay-php-sdk/' . $this->sdkVersion, 'api/' . $this->apiVersion];
        foreach (self::SLOT_ORDER as $slot) {
            if (!isset($this->slots[$slot])) {
                continue;
            }

            $token = $this->slots[$slot]['name'] . '/' . $this->slots[$slot]['version'];
            // A User-Agent is an ASCII field, and a Cyrillic product name would otherwise
            // put raw UTF-8 bytes into it. The JSON header carries the slot losslessly
            // either way, so an absent token beats a mangled one.
            if (preg_match('/^[\x20-\x7E]+$/', $token) === 1) {
                $parts[] = $token;
            }
        }

        return implode(' ', $parts);
    }

    /**
     * api_version is the Unitpay API surface targeted; platform is the coarse OS family
     * only, never the full uname. Each filled slot is an object, so a consumer never has to
     * guess where the name ends and the version begins.
     *
     * @return string|null null when the payload could not be encoded at all — see below
     */
    private function clientJson(): ?string
    {
        $client = [
            'sdk_version'  => $this->sdkVersion,
            'api_version'  => $this->apiVersion,
            'lang'         => 'php',
            'lang_version' => PHP_VERSION,
            'platform'     => PHP_OS_FAMILY,
            'publisher'    => 'unitpay',
        ];
        foreach (self::SLOT_ORDER as $slot) {
            if (isset($this->slots[$slot])) {
                $client[$slot] = $this->slots[$slot];
            }
        }

        // JSON_UNESCAPED_SLASHES only so a name carrying a slash reads normally. Deliberately
        // no JSON_UNESCAPED_UNICODE: the \uXXXX escaping is what keeps this header value pure
        // ASCII, which an HTTP header has to be.
        //
        // JSON_INVALID_UTF8_SUBSTITUTE is what keeps one bad byte from costing the whole
        // payload: a legacy windows-1251 CMS name used to make json_encode return false,
        // which the old `(string)` cast turned into an empty header — losing sdk_version and
        // the PHP version along with the slot that caused it.
        $json = json_encode($client, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        // Belt and braces: with the substitute flag a false return is all but unreachable,
        // but if it happens the header is omitted rather than sent blank.
        return $json === false ? null : $json;
    }
}
