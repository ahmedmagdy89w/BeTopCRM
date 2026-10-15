<?php

namespace App\Services;

class WebhookDestinationValidator
{
    public function isAllowed(string $url): bool
    {
        $parts = parse_url($url);
        $host = isset($parts['host']) ? trim($parts['host'], '[]') : '';

        if (($parts['scheme'] ?? null) !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses = [$host];
        } else {
            $records = dns_get_record($host, DNS_A | DNS_AAAA);
            $addresses = [];

            foreach ($records ?: [] as $record) {
                $address = $record['ip'] ?? $record['ipv6'] ?? null;

                if ($address !== null) {
                    $addresses[] = $address;
                }
            }
        }

        if ($addresses === []) {
            return false;
        }

        foreach (array_unique($addresses) as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}
