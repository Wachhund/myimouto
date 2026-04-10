<?php

class UploadWhitelist extends Rails\ActiveRecord\Base
{
    public static function tableName()
    {
        return 'upload_whitelists';
    }

    protected function validations()
    {
        return [
            'pattern' => ['presence' => true],
        ];
    }

    protected function callbacks()
    {
        return [
            'before_create' => ['set_created_at'],
            'before_save' => ['set_updated_at'],
        ];
    }

    protected function set_created_at()
    {
        if (!$this->created_at) {
            $this->created_at = date('Y-m-d H:i:s');
        }
    }

    protected function set_updated_at()
    {
        $this->updated_at = date('Y-m-d H:i:s');
    }

    /**
     * Check whether a URL is allowed by the upload whitelist.
     *
     * Deny rules (allowed = 0) take priority over allow rules.
     * If no rule matches, the default is to deny.
     *
     * @param string $url
     * @return array{allowed: bool, reason: string, resolved_ip?: string}
     */
    public static function is_allowed($url)
    {
        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            return ['allowed' => false, 'reason' => 'Invalid URL'];
        }

        $scheme = $parsed['scheme'] ?? '';
        if (!in_array($scheme, ['http', 'https'])) {
            return ['allowed' => false, 'reason' => 'Only http/https URLs are allowed'];
        }

        $host = strtolower($parsed['host']);

        // Resolve hostname to IP and validate against private ranges (SSRF prevention).
        // The resolved IP is returned so callers can pin cURL to it (mitigating DNS rebinding).
        $resolved_ip = self::resolve_and_validate_host($host);
        if ($resolved_ip === false) {
            return ['allowed' => false, 'reason' => 'Private/internal addresses are not allowed'];
        }

        // Check deny rules first (higher priority)
        $deny = self::where("allowed = 0 AND ? LIKE REPLACE(pattern, '*', '%')", $host)->first();
        if ($deny) {
            return ['allowed' => false, 'reason' => $deny->reason ?: 'Domain is blocked'];
        }

        // Check allow rules
        $allow = self::where("allowed = 1 AND ? LIKE REPLACE(pattern, '*', '%')", $host)->first();
        if ($allow) {
            return ['allowed' => true, 'reason' => 'Domain is whitelisted', 'resolved_ip' => $resolved_ip];
        }

        // Default deny
        return ['allowed' => false, 'reason' => 'Domain is not whitelisted'];
    }

    /**
     * Resolve a hostname to an IP and validate it is not private/internal.
     *
     * Checks both A (IPv4) and AAAA (IPv6) records. Also catches
     * IPv4-mapped IPv6 addresses (::ffff:x.x.x.x) and localhost.
     *
     * @param string $host
     * @return string|false The resolved IP, or false if blocked
     */
    public static function resolve_and_validate_host(string $host)
    {
        // Direct IP input
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::validate_ip($host) ? $host : false;
        }

        // Resolve A record
        $ip = gethostbyname($host);
        // gethostbyname returns the hostname unchanged on failure
        if ($ip === $host) {
            // Try AAAA
            $records = @dns_get_record($host, DNS_AAAA);
            if (!empty($records)) {
                $ip = $records[0]['ipv6'] ?? null;
            }
            if (!$ip) {
                return false; // Unresolvable
            }
        }

        return self::validate_ip($ip) ? $ip : false;
    }

    /**
     * Validate that an IP is not private, reserved, loopback, or IPv4-mapped-IPv6.
     *
     * @param string $ip
     * @return bool
     */
    private static function validate_ip(string $ip): bool
    {
        // Block loopback explicitly (covers 127.x.x.x and ::1)
        if ($ip === '::1' || str_starts_with($ip, '127.')) {
            return false;
        }

        // Block IPv4-mapped IPv6 (::ffff:10.0.0.1)
        if (stripos($ip, '::ffff:') === 0) {
            $mapped = substr($ip, 7);
            if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        // Standard private/reserved check
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        return true;
    }

    public function api_attributes()
    {
        return [
            'id' => (int) $this->id,
            'pattern' => $this->pattern,
            'allowed' => (bool) $this->allowed,
            'reason' => $this->reason,
            'hidden' => (bool) $this->hidden,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
