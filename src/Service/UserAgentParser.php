<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use DeviceDetector\DeviceDetector;

class UserAgentParser implements UserAgentParserInterface
{
    public function describe(?string $userAgent): ?string
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return null;
        }

        $detector = new DeviceDetector($userAgent);
        $detector->parse();

        $client = $this->normalize($detector->getClient('name'));
        $os = $this->normalize($detector->getOs('name'));

        return match (true) {
            $client !== null && $os !== null => sprintf('%s on %s', $client, $os),
            $client !== null => $client,
            $os !== null => $os,
            default => null,
        };
    }

    private function normalize(mixed $value): ?string
    {
        if (!is_string($value) || $value === '' || $value === 'UNK') {
            return null;
        }

        return $value;
    }
}
