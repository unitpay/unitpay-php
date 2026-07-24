<?php

namespace Unitpay\Webhook;

/**
 * Checks whether an IP is in the allowlist: exact addresses and CIDR subnets
 * (IPv4 and IPv6). Kept as a separate class so the range-matching logic stays
 * cohesive and testable, and so malformed feed JSON can never empty the allowlist.
 */
final class IpAllowlist
{
    /** @var string[] */
    private array $entries;

    /**
     * @param string[] $entries exact IPs and/or CIDR ranges (e.g. "77.75.153.0/25")
     */
    public function __construct(array $entries)
    {
        $this->entries = $entries;
    }

    public function contains(string $ip): bool
    {
        $ipBin = $this->toBinary($ip);
        foreach ($this->entries as $entry) {
            if (strpos($entry, '/') === false) {
                if ($entry === $ip) {
                    return true;
                }
                // Normalized comparison: the same address written differently
                // (case/IPv6 compression) yields the same packed in_addr.
                if ($ipBin !== null) {
                    $entryBin = $this->toBinary($entry);
                    if ($entryBin !== null && $entryBin === $ipBin) {
                        return true;
                    }
                }
                continue;
            }
            if ($ipBin !== null && $this->cidrContains($entry, $ipBin)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $ipBin packed in_addr of the client IP (from toBinary())
     */
    private function cidrContains(string $cidr, string $ipBin): bool
    {
        list($subnet, $bits) = explode('/', $cidr, 2);
        if (!ctype_digit($bits)) {
            return false;
        }
        $subnetBin = $this->toBinary($subnet);
        if ($subnetBin === null || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }
        return $this->prefixMatches($ipBin, $subnetBin, (int) $bits);
    }

    /**
     * Whether $entry is a valid allowlist entry: an exact IPv4/IPv6 address or a
     * CIDR range of the form "address/bits". Used to validate a fetched IP list
     * before it replaces the built-in one, so malformed JSON cannot empty the
     * allowlist.
     */
    public static function isValidEntry(string $entry): bool
    {
        if (strpos($entry, '/') === false) {
            return filter_var($entry, FILTER_VALIDATE_IP) !== false;
        }
        list($subnet, $bits) = explode('/', $entry, 2);
        if (!ctype_digit($bits) || filter_var($subnet, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        // The prefix length cannot exceed the address width (IPv4 = 32, IPv6 = 128),
        // otherwise the entry looks valid but matches nothing (prefixMatches returns false).
        $maxBits = filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 128 : 32;
        return (int) $bits <= $maxBits;
    }

    /**
     * Parses the body of the published webhook IP feed ({"webhooks":[...]}) into a
     * validated, de-duplicated list of entries. Returns null on empty input,
     * malformed JSON, a missing or non-array "webhooks" key, or when no entry is a
     * valid IP/CIDR — so a bad feed cannot empty the allowlist.
     * @return string[]|null
     */
    public static function parseWebhooksFeed(string $body): ?array
    {
        if ($body === '') {
            return null;
        }
        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['webhooks']) || !is_array($data['webhooks'])) {
            return null;
        }
        $valid = [];
        foreach ($data['webhooks'] as $entry) {
            if (is_string($entry) && self::isValidEntry($entry)) {
                $valid[] = $entry;
            }
        }
        return $valid === [] ? null : array_values(array_unique($valid));
    }

    /**
     * @return string|null packed in_addr, or null if $ip is not a valid address
     */
    private function toBinary(string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        $binary = inet_pton($ip);
        return $binary === false ? null : $binary;
    }

    private function prefixMatches(string $ipBin, string $subnetBin, int $bits): bool
    {
        if ($bits > strlen($ipBin) * 8) {
            return false;
        }
        $whole = intdiv($bits, 8);
        if ($whole > 0 && strncmp($ipBin, $subnetBin, $whole) !== 0) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = chr((0xff << (8 - $rest)) & 0xff);
        return ($ipBin[$whole] & $mask) === ($subnetBin[$whole] & $mask);
    }
}
