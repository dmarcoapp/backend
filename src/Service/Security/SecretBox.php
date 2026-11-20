<?php

declare(strict_types=1);

namespace App\Service\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Encrypts the secrets DMARCo keeps in its database, such as mailbox and SMTP
 * passwords, with a key that is kept apart from the database, so a database
 * dump alone gives none of them away.
 *
 * Each secret is bound to the place it is stored in, given as its context, so
 * a value copied into another column or row does not decrypt there. The output
 * names the format version and the key, which leaves room for rotating either.
 */
final readonly class SecretBox
{
    private const string VERSION = 'v1';
    private const int KEY_HEX_LENGTH = 2 * SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES;

    private string $key;

    public function __construct(
        #[Autowire(env: 'default::APP_ENCRYPTION_KEY')]
        #[\SensitiveParameter]
        ?string $encryptionKey,
    ) {
        $this->key = trim((string) $encryptionKey);
    }

    /**
     * False until the installer has generated a key, which is the state an
     * installation upgraded without it is in.
     */
    public function isConfigured(): bool
    {
        return '' !== $this->key;
    }

    public function encrypt(#[\SensitiveParameter] string $plaintext, string $context): string
    {
        $key = $this->binaryKey();
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $context, $nonce, $key);

        return implode(':', [self::VERSION, self::keyId($key), sodium_bin2base64($nonce.$ciphertext, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING)]);
    }

    /**
     * @throws UnreadableSecretException
     */
    public function decrypt(string $encrypted, string $context): string
    {
        $key = $this->binaryKey();

        $parts = explode(':', $encrypted);
        if (3 !== count($parts) || self::VERSION !== $parts[0]) {
            throw new UnreadableSecretException('The stored secret is not in a format this version reads.');
        }

        if (!hash_equals(self::keyId($key), $parts[1])) {
            throw new UnreadableSecretException('The stored secret was encrypted with another APP_ENCRYPTION_KEY.');
        }

        try {
            $payload = sodium_base642bin($parts[2], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (\SodiumException $e) {
            throw new UnreadableSecretException('The stored secret is damaged.', previous: $e);
        }

        $nonce = substr($payload, 0, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = substr($payload, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        if (SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES !== strlen($nonce) || '' === $ciphertext) {
            throw new UnreadableSecretException('The stored secret is damaged.');
        }

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, $context, $nonce, $key);
        if (false === $plaintext) {
            throw new UnreadableSecretException('The stored secret is damaged, or belongs somewhere else.');
        }

        return $plaintext;
    }

    private function binaryKey(): string
    {
        if (!$this->isConfigured()) {
            throw new \LogicException('APP_ENCRYPTION_KEY is not set. Run the installer again to generate it.');
        }

        if (self::KEY_HEX_LENGTH !== strlen($this->key) || !ctype_xdigit($this->key)) {
            throw new \LogicException('APP_ENCRYPTION_KEY must be 64 hexadecimal characters, which is what "openssl rand -hex 32" prints.');
        }

        return sodium_hex2bin($this->key);
    }

    /**
     * Names the key without giving anything about it away.
     */
    private static function keyId(string $key): string
    {
        return substr(hash('sha256', 'dmarco-secret-box-key-id'.$key), 0, 8);
    }
}
