<?php

namespace Unitpay\Signature;

use Unitpay\Exception\UnitpayValidationException;

/**
 * Builds and serializes the SHA-256 request/webhook signature. Kept as a single
 * class so there is exactly one place that assembles the "{up}"-joined payload and
 * appends the secret — used by both outbound signing and inbound verification.
 */
final class SignatureBuilder
{
    /**
     * Builds the SHA-256 signature: parameter values sorted with ksort and joined
     * by the literal "{up}" delimiter, with $method prepended and $secretKey
     * appended.
     *
     * Security: unset() strips the caller-supplied signature keys AND the PHP_INT_MAX
     * index — a forged params[PHP_INT_MAX] would turn the secret append into a
     * no-op, dropping the secret from the hash and making signatures forgeable (bypass
     * on PHP <8, fatal Error/DoS on PHP >=8). Do NOT remove this unset — the guard was
     * once lost in 7835fb4 and restored. A forged webhook may also inject an array
     * value (e.g. params[x][]=1), so non-scalars are coerced to '' — implode() emits no
     * warning and verification still fails, because the secret is appended regardless.
     *
     * A null/empty secret is rejected up front: as a public entry point it must not
     * silently hash with an empty secret (is_scalar(null) is false, so the appended
     * key would coerce to '' and drop out).
     *
     * @param array<array-key, mixed> $params
     * @throws UnitpayValidationException when the secret key is unset/empty
     */
    public function build(array $params, ?string $secretKey, ?string $method = null): string
    {
        if (empty($secretKey)) {
            throw new UnitpayValidationException('SecretKey is null');
        }
        unset($params['sign'], $params['signature'], $params[PHP_INT_MAX]);
        ksort($params);
        $params[] = $secretKey;

        if ($method !== null) {
            array_unshift($params, $method);
        }

        $params = array_map(static function ($value) {
            if (is_float($value)) {
                return self::floatToString($value);
            }
            return is_scalar($value) ? $value : '';
        }, $params);

        return hash('sha256', implode('{up}', $params));
    }

    /**
     * Converts float params to locale-independent decimal strings so the signature and
     * request URL match on PHP <8.0 (where (string)$float honors LC_NUMERIC and would
     * yield "100,5" in comma locales). Non-float values pass through unchanged.
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function stringifyFloats(array $params): array
    {
        foreach ($params as $key => $value) {
            if (is_float($value)) {
                $params[$key] = self::floatToString($value);
            }
        }

        return $params;
    }

    /**
     * Converts a float to a locale-independent decimal string without trailing zeros.
     * (string) $float honors LC_NUMERIC on PHP <8.0 and would yield "100,5" in comma
     * locales, breaking the signature/URL match. Shared by build() and
     * stringifyFloats() so the signature and the transmitted value look identical.
     */
    public static function floatToString(float $value): string
    {
        return rtrim(rtrim(sprintf('%.8F', $value), '0'), '.');
    }
}
