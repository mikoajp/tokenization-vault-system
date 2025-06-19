<?php

namespace App\Services;

use App\Exceptions\EncryptionException;

class EncryptionService
{
    private const ALGORITHM = 'AES-256-GCM';
    private const KEY_LENGTH = 32; // 256 bits

    /**
     * Encrypt sensitive data using AES-256-GCM
     */
    public function encrypt(string $data, string $keyReference = null): array
    {
        try {
            $key = $this->getEncryptionKey($keyReference);
            $iv = random_bytes(16); // 128-bit IV for GCM
            $tag = '';

            $encrypted = openssl_encrypt(
                $data,
                self::ALGORITHM,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            if ($encrypted === false) {
                throw new EncryptionException('Encryption failed');
            }

            return [
                'encrypted_data' => base64_encode($encrypted),
                'iv' => base64_encode($iv),
                'tag' => base64_encode($tag),
                'algorithm' => self::ALGORITHM,
                'key_reference' => $keyReference ?? 'default'
            ];

        } catch (\Exception $e) {
            throw new EncryptionException('Encryption error: ' . $e->getMessage());
        }
    }

    /**
     * Decrypt sensitive data
     */
    public function decrypt(array $encryptedData): string
    {
        try {
            $key = $this->getEncryptionKey($encryptedData['key_reference'] ?? null);

            $decrypted = openssl_decrypt(
                base64_decode($encryptedData['encrypted_data']),
                $encryptedData['algorithm'] ?? self::ALGORITHM,
                $key,
                OPENSSL_RAW_DATA,
                base64_decode($encryptedData['iv']),
                base64_decode($encryptedData['tag'] ?? '')
            );

            if ($decrypted === false) {
                throw new EncryptionException('Decryption failed');
            }

            return $decrypted;

        } catch (\Exception $e) {
            throw new EncryptionException('Decryption error: ' . $e->getMessage());
        }
    }

    /**
     * Generate data hash for integrity verification
     */
    public function generateDataHash(string $data): string
    {
        return hash('sha256', $data);
    }

    /**
     * Generate checksum for token integrity
     */
    public function generateChecksum(string $tokenValue, string $dataHash): string
    {
        return hash('sha256', $tokenValue . $dataHash . config('app.key'));
    }

    /**
     * Verify token integrity
     */
    public function verifyChecksum(string $tokenValue, string $dataHash, string $checksum): bool
    {
        $expectedChecksum = $this->generateChecksum($tokenValue, $dataHash);
        return hash_equals($expectedChecksum, $checksum);
    }

    /**
     * Get encryption key (in production, this would integrate with HSM/key management)
     */
    private function getEncryptionKey(string $keyReference = null): string
    {
        // In production, this would fetch from secure key management system
        $baseKey = config('app.key');
        $keyReference = $keyReference ?? 'default';

        // Derive key using HKDF
        return hash_hkdf('sha256', $baseKey, self::KEY_LENGTH, $keyReference, 'vault-encryption');
    }

    /**
     * Generate secure random token
     */
    public function generateSecureToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Generate format-preserving token (simplified implementation)
     */
    public function generateFormatPreservingToken(string $originalData): string
    {
        $pattern = $this->analyzeFormat($originalData);
        return $this->generateTokenFromPattern($pattern);
    }

    /**
     * Analyze data format for format-preserving encryption
     */
    private function analyzeFormat(string $data): array
    {
        $pattern = [];
        for ($i = 0; $i < strlen($data); $i++) {
            $char = $data[$i];
            if (ctype_digit($char)) {
                $pattern[] = 'D'; // Digit
            } elseif (ctype_alpha($char)) {
                $pattern[] = ctype_upper($char) ? 'U' : 'L'; // Upper/Lower
            } else {
                $pattern[] = $char; // Literal character
            }
        }
        return $pattern;
    }

    /**
     * Generate token matching format pattern
     */
    private function generateTokenFromPattern(array $pattern): string
    {
        $result = '';
        foreach ($pattern as $p) {
            switch ($p) {
                case 'D':
                    $result .= random_int(0, 9);
                    break;
                case 'U':
                    $result .= chr(random_int(65, 90)); // A-Z
                    break;
                case 'L':
                    $result .= chr(random_int(97, 122)); // a-z
                    break;
                default:
                    $result .= $p; // Literal character
                    break;
            }
        }
        return $result;
    }
}
