<?php

namespace Unitpay\Telemetry;

/**
 * Builds the anonymous self-identification headers sent with every service call, and holds
 * the integration identity a merchant can fill in.
 *
 * Two things are reported, and the division is the only one an integrator cannot get wrong:
 * setModule() names what they wrote, setStack() names what it runs on. Neither asks anyone to
 * classify their environment. A stack entry is a known product — WordPress, WooCommerce,
 * Bitrix, Laravel — that a server can categorise from its name with a table it can fix
 * without shipping new SDKs. A module name is arbitrary by nature and no table can recognise
 * it, which is exactly why it keeps a field of its own.
 *
 * The runtime is not part of the stack: lang, lang_version and platform are filled in here.
 * The rule worth stating to an integrator is "if the SDK can find it out itself, it is not
 * stack" — so PHP never appears in a PHP stack, but WooCommerce does.
 *
 * Every value is a product name and version supplied by the integrator — no PII, no
 * identifiers, no separate telemetry request. Nothing here throws: these setters run in a
 * payment bootstrap, so an unusable value is dropped rather than rejected.
 *
 * Mutable and shared by reference, like Api\PendingParams: services are created lazily and
 * cached by the facade, so a setModule() call made after the first payments() call still has
 * to reach the service that already exists.
 */
final class ClientInfo
{
    /**
     * Three fixed slots bounded the header for free; an ordered list does not. Each entry is
     * already capped below, but nothing else would stop a caller from passing fifty of them
     * and building a header an intermediary may reject. Eight is far above any real stack.
     */
    private const MAX_STACK_ENTRIES = 8;

    /** Byte caps, generous for a real product name but enough to bound the header. */
    private const MAX_NAME_BYTES = 64;
    private const MAX_VERSION_BYTES = 32;

    private string $sdkVersion;
    private string $apiVersion;
    private bool $enabled = true;

    /**
     * What the integrator wrote.
     *
     * @var array{name: string, version: string}|null
     */
    private ?array $module = null;

    /**
     * What it runs on, outermost host first. The two halves of an entry are kept apart rather
     * than joined, because a name may legitimately contain the separator — a Composer package
     * name such as "unitpay/woocommerce" would make "unitpay/woocommerce/2.1" impossible to
     * split back reliably.
     *
     * @var array<int, array{name: string, version: string}>
     */
    private array $stack = [];

    public function __construct(string $sdkVersion, string $apiVersion)
    {
        $this->sdkVersion = $sdkVersion;
        $this->apiVersion = $apiVersion;
    }

    /**
     * Names the integration itself — the module, plugin, template or application that wraps
     * this SDK.
     *
     * A value that cannot produce a meaningful token is dropped, not rejected. This runs in
     * an integration's bootstrap, so raising here would let a cosmetic concern abort a
     * checkout — and a CMS that stops exposing its version string is an ordinary outcome of a
     * CMS update, not an exceptional one.
     */
    public function setModule(string $name, string $version): void
    {
        $entry = self::entry($name, $version);
        if ($entry === null) {
            // Returning leaves any earlier value in place, so a setter that starts coming back
            // empty costs the update rather than the identity already reported.
            return;
        }

        $this->module = $entry;
    }

    /**
     * Replaces the whole stack: the products this integration runs on, outermost host first.
     *
     * Replacement rather than appending, so the call is idempotent and safe in a bootstrap
     * that may execute twice. Someone who forked a template adds a line to the same array,
     * which is the case an append method would have existed for — and append can be
     * introduced later, while removing it could not.
     *
     * Order is presentation only: it makes the User-Agent read like the stack it describes,
     * and grouping happens by name, which is order-independent.
     *
     * @param array<string|int, scalar|null> $stack product name => version. A non-string key
     *                                              means a list was passed by mistake and is
     *                                              dropped rather than turned into a product
     *                                              literally named "0".
     */
    public function setStack(array $stack): void
    {
        $this->stack = [];
        foreach ($stack as $name => $version) {
            if (count($this->stack) >= self::MAX_STACK_ENTRIES) {
                break;
            }
            if (!is_string($name) || !is_scalar($version)) {
                continue;
            }

            $entry = self::entry($name, (string) $version);
            if ($entry !== null) {
                $this->stack[] = $entry;
            }
        }
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
     * Cleans both halves and refuses the pair when either is unusable.
     *
     * @return array{name: string, version: string}|null
     */
    private static function entry(string $name, string $version): ?array
    {
        $name = self::clean($name, self::MAX_NAME_BYTES);
        $version = self::clean($version, self::MAX_VERSION_BYTES);
        if ($name === '' || $version === '') {
            // A blank half would emit a meaningless "/1.0" or "Bitrix/" token.
            return null;
        }

        return ['name' => $name, 'version' => $version];
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
     * The only place the two halves are joined. A User-Agent cannot carry structure, so its
     * tokens stay the human-readable "Name/Version" form and the JSON header is what a parser
     * should read. The module goes last, being the narrowest context of all.
     */
    private function userAgent(): string
    {
        $parts = ['unitpay-php-sdk/' . $this->sdkVersion, 'api/' . $this->apiVersion];

        $entries = $this->stack;
        if ($this->module !== null) {
            $entries[] = $this->module;
        }

        foreach ($entries as $entry) {
            $token = $entry['name'] . '/' . $entry['version'];
            // A User-Agent is an ASCII field, and a Cyrillic product name would otherwise put
            // raw UTF-8 bytes into it. The JSON header carries the entry losslessly either
            // way, so an absent token beats a mangled one.
            if (preg_match('/^[\x20-\x7E]+$/', $token) === 1) {
                $parts[] = $token;
            }
        }

        return implode(' ', $parts);
    }

    /**
     * api_version is the Unitpay API surface targeted; platform is the coarse OS family only,
     * never the full uname. The module is an object and the stack an ordered array of them, so
     * a consumer never has to guess where a name ends and a version begins. An empty one is
     * omitted rather than sent empty.
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
        if ($this->module !== null) {
            $client['module'] = $this->module;
        }
        if ($this->stack !== []) {
            $client['stack'] = $this->stack;
        }

        // JSON_UNESCAPED_SLASHES only so a name carrying a slash reads normally. Deliberately
        // no JSON_UNESCAPED_UNICODE: the \uXXXX escaping is what keeps this header value pure
        // ASCII, which an HTTP header has to be.
        //
        // JSON_INVALID_UTF8_SUBSTITUTE is what keeps one bad byte from costing the whole
        // payload: a legacy windows-1251 CMS name used to make json_encode return false,
        // which the old `(string)` cast turned into an empty header — losing sdk_version and
        // the PHP version along with the entry that caused it.
        $json = json_encode($client, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        // Belt and braces: with the substitute flag a false return is all but unreachable, but
        // if it happens the header is omitted rather than sent blank.
        return $json === false ? null : $json;
    }
}
