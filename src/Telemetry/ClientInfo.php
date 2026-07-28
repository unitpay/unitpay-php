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
     * @param string $slot one of the SLOT_* constants
     * @throws UnitpayValidationException on an unknown slot or an empty name/version
     */
    public function setSlot(string $slot, string $name, string $version): void
    {
        if (!in_array($slot, self::SLOT_ORDER, true)) {
            throw new UnitpayValidationException(
                sprintf('Unknown telemetry slot "%s".', $slot)
            );
        }
        if (trim($name) === '' || trim($version) === '') {
            // A blank half would emit a meaningless "/1.0" or "Bitrix/" token.
            throw new UnitpayValidationException(
                sprintf('Telemetry slot "%s" needs both a name and a version.', $slot)
            );
        }

        $this->slots[$slot] = ['name' => trim($name), 'version' => trim($version)];
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

        return [
            'User-Agent: ' . $this->userAgent(),
            'Unitpay-Client: ' . $this->clientJson(),
        ];
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
            if (isset($this->slots[$slot])) {
                $parts[] = $this->slots[$slot]['name'] . '/' . $this->slots[$slot]['version'];
            }
        }

        return implode(' ', $parts);
    }

    /**
     * api_version is the Unitpay API surface targeted; platform is the coarse OS family
     * only, never the full uname. Each filled slot is an object, so a consumer never has to
     * guess where the name ends and the version begins.
     */
    private function clientJson(): string
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
        return (string) json_encode($client, JSON_UNESCAPED_SLASHES);
    }
}
