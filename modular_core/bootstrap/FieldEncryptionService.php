<?php

namespace ModularCore\Bootstrap;

use Exception;

/**
 * Requirement 12: Encrypt Sensitive Database Fields
 */
class FieldEncryptionService
{
    private $key;
    private $cipher = 'aes-256-gcm';

    public function __construct(string $masterKey = null)
    {
        # Requirement 12.6: Load from Environment Variables
        $this->key = $masterKey ?: env('APP_MASTER_KEY');
        if (substr($this->key, 0, 7) === 'base64:') {
            $this->key = base64_decode(substr($this->key, 7));
        }
    }

    /**
     * Requirement 12.1-12.5: AES-256-GCM Encryption
     */
    public function encrypt(string $value): string
    {
        $ivlen = openssl_cipher_iv_length($this->cipher);
        # Requirement 12.7: Unique initialization vector (IV) per value
        $iv = openssl_random_pseudo_bytes($ivlen);
        $tag = ""; 

        $ciphertext = openssl_encrypt(
            $value, 
            $this->cipher, 
            $this->key, 
            $options=0, 
            $iv, 
            $tag
        );

        # Combined Payload: IV:TAG:CIPHERTEXT
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Requirement 12.8: Transparent Decryption for authorized requests
     */
    public function decrypt(string $payload): string
    {
        $data = base64_decode($payload);
        $ivlen = openssl_cipher_iv_length($this->cipher);
        
        $iv = substr($data, 0, $ivlen);
        $tag = substr($data, $ivlen, 16); # AES-GCM default tag is 16 bytes
        $ciphertext = substr($data, $ivlen + 16);

        $decrypted = openssl_decrypt(
            $ciphertext, 
            $this->cipher, 
            $this->key, 
            $options=0, 
            $iv, 
            $tag
        );

        if ($decrypted === false) {
            throw new Exception("CRITICAL SECURITY ERROR: Decryption failed. Key mismatch or payload corruption.");
        }

        return $decrypted;
    }

    /**
     * Requirement 12.9: Key Rotation Support
     */
    public function reEncrypt(string $payload, string $oldKey, string $newKey): string
    {
        $service = new self($oldKey);
        $plaintext = $service->decrypt($payload);
        
        $newService = new self($newKey);
        return $newService->encrypt($plaintext);
    }
}
