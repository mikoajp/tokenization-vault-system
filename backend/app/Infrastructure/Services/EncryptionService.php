<?php

namespace App\Infrastructure\Services;

use App\Domain\Vault\ValueObjects\EncryptionConfig;
use App\Shared\Exceptions\EncryptionException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;

class EncryptionService
{
    public function generateKeyReference(): string
    {
        return 'key_' . Str::uuid() . '_' . time();
    }

    public function encrypt(string $data, EncryptionConfig $config): string
    {
        try {
            switch ($config->getAlgorithm()) {
                case 'AES-256-GCM':
                    return $this->encryptAesGcm($data, $config->getKeyReference());
                
                case 'AES-256-CBC':
                    return $this->encryptAesCbc($data, $config->getKeyReference());
                
                case 'ChaCha20-Poly1305':
                    return $this->encryptChaCha20($data, $config->getKeyReference());
                
                default:
                    throw new EncryptionException("Unsupported encryption algorithm: {$config->getAlgorithm()}");
            }
        } catch (\Exception $e) {
            throw new EncryptionException("Encryption failed: {$e->getMessage()}", 0, $e);
        }
    }

    public function decrypt(string $encryptedData, EncryptionConfig $config): string
    {
        try {
            switch ($config->getAlgorithm()) {
                case 'AES-256-GCM':
                    return $this->decryptAesGcm($encryptedData, $config->getKeyReference());
                
                case 'AES-256-CBC':
                    return $this->decryptAesCbc($encryptedData, $config->getKeyReference());
                
                case 'ChaCha20-Poly1305':
                    return $this->decryptChaCha20($encryptedData, $config->getKeyReference());
                
                default:
                    throw new EncryptionException("Unsupported encryption algorithm: {$config->getAlgorithm()}");
            }
        } catch (\Exception $e) {
            throw new EncryptionException("Decryption failed: {$e->getMessage()}", 0, $e);
        }
    }

    public function generateDataHash(string $data): string
    {
        return hash('sha256', $data);
    }

    public function generateChecksum(string $data): string
    {
        return hash('crc32', $data);
    }

    private function encryptAesGcm(string $data, string $keyReference): string
    {
        // In production, retrieve actual key from secure key management system
        $key = $this->getEncryptionKey($keyReference);
        
        // For now, use Laravel's encryption (which uses AES-256-CBC)
        // In production, implement proper AES-GCM
        return Crypt::encrypt($data);
    }

    private function decryptAesGcm(string $encryptedData, string $keyReference): string
    {
        $key = $this->getEncryptionKey($keyReference);
        
        return Crypt::decrypt($encryptedData);
    }

    private function encryptAesCbc(string $data, string $keyReference): string
    {
        return Crypt::encrypt($data);
    }

    private function decryptAesCbc(string $encryptedData, string $keyReference): string
    {
        return Crypt::decrypt($encryptedData);
    }

    private function encryptChaCha20(string $data, string $keyReference): string
    {
        // Placeholder for ChaCha20-Poly1305 implementation
        return Crypt::encrypt($data);
    }

    private function decryptChaCha20(string $encryptedData, string $keyReference): string
    {
        return Crypt::decrypt($encryptedData);
    }

    private function getEncryptionKey(string $keyReference): string
    {
        // In production, this would retrieve the actual encryption key
        // from a secure key management system (AWS KMS, HashiCorp Vault, etc.)
        // For now, return a derived key
        return hash('sha256', config('app.key') . $keyReference);
    }
}