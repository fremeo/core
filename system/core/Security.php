<?php
namespace fremeo\core;

class Security {
    public bool $SecurityActive = true;
    private string $secretHashValue;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('fremeo');
            session_start();
        }

        if (!isset($_SESSION['Security'])) {
            $_SESSION['Security'] = [];
        }

        if (empty($_SESSION['Security']['SecretHashValue'])) {
            $_SESSION['Security']['SecretHashValue'] = bin2hex(random_bytes(32));
        }

        $this->secretHashValue = $_SESSION['Security']['SecretHashValue'];
    }

    public function getSecurityD(string $StringValue): string {
        return (string) crc32($this->secretHashValue . $StringValue);
    }

    public function registerSecurityD(string $StringValue): string {
        if($this->SecurityActive) {
            $hash = $this->getSecurityD($StringValue);
            $_SESSION['Security']['Map'][$hash] = $StringValue;
            $hash = 'SD['.$hash.']';
        } else {
           $hash = $StringValue;
        }
        return $hash;
    }

    public function resolveSecureData(array $secureData = [], array $baseData = []): array {
        $result = $baseData;

        foreach ((array) $secureData as $hash => $value) {
            $key = $_SESSION['Security']['Map'][$hash] ?? null;
            if ($key === null || $key === '') {
                continue;
            }

            $this->setNestedValue($result, $key, $value);
        }

        return $result;
    }

    private function setNestedValue(array &$target, string $path, $value): void {
        preg_match_all('/\[?([A-Za-z0-9_:-]+)\]?/', $path, $matches);
        $keys = $matches[1] ?? [];

        if ($keys && $keys[0] === 'D') {
            array_shift($keys);
        }

        if (empty($keys)) {
            return;
        }

        $ref = &$target;
        foreach ($keys as $key) {
            if (!isset($ref[$key]) || !is_array($ref[$key])) {
                $ref[$key] = [];
            }
            $ref = &$ref[$key];
        }

        $ref = $value;
    }
}
