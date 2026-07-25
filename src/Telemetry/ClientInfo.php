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
    /** @var array<string, string> slot name => "Product/version" */
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

        $this->slots[$slot] = trim($name) . '/' . trim($version);
    }

    /**
     * Stops sending X-Unitpay-Client entirely. The User-Agent keeps the SDK version alone,
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
            'X-Unitpay-Client: ' . $this->clientJson(),
        ];
    }

    private function userAgent(): string
    {
        $parts = ['unitpay-php-sdk/' . $this->sdkVersion, 'api/' . $this->apiVersion];
        foreach (self::SLOT_ORDER as $slot) {
            if (isset($this->slots[$slot])) {
                $parts[] = $this->slots[$slot];
            }
        }

        return implode(' ', $parts);
    }

    /**
     * api_version is the Unitpay API surface targeted; platform is the coarse OS family
     * only, never the full uname.
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

        return (string) json_encode($client);
    }
}
