<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Service\Passkey;

use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\TextStringObject;
use CBOR\UnsignedIntegerObject;

/**
 * PHP-side WebAuthn authenticator emulator for Behat ceremony tests.
 *
 * Generates real ES256 (P-256 ECDSA SHA-256) keypairs in PHP, encodes
 * the WebAuthn structures (clientDataJSON, authenticatorData, attestationObject,
 * COSE public key) exactly as a real authenticator would, and produces a
 * JSON-encoded `PublicKeyCredential` payload identical in shape to what
 * `passkey.js` posts to the server. The server-side `web-auth/webauthn-lib`
 * validator then verifies the signature against the embedded public key —
 * no mock, real crypto.
 *
 * Keypairs persist across kernel reboots within a Behat scenario via a
 * static property; this lets a "register" step plant a credential and a
 * subsequent "login" step re-use the same private key to sign the assertion.
 */
class FakeAuthenticator
{
    /**
     * @var array<string, array{privateKeyPem: string, publicKeyX: string, publicKeyY: string, signCount: int}>
     *
     * Keyed by credential ID (raw bytes, not base64url).
     */
    protected static array $credentials = [];

    /** @return array<string, mixed> shape produced by passkey.js encodeAttestationCredential() */
    public function createRegistrationResponse(
        array $options,
        string $origin,
        bool $userVerified = true,
    ): array {
        $rpId = (string) ($options['rp']['id'] ?? parse_url($origin, PHP_URL_HOST));
        $challenge = self::base64UrlDecode((string) $options['challenge']);

        ['privateKeyPem' => $privateKeyPem, 'publicKeyX' => $x, 'publicKeyY' => $y] = $this->generateKeypair();
        $credentialId = random_bytes(32);

        self::$credentials[$credentialId] = [
            'privateKeyPem' => $privateKeyPem,
            'publicKeyX' => $x,
            'publicKeyY' => $y,
            'signCount' => 1,
        ];

        $clientDataJson = $this->buildClientDataJson('webauthn.create', $challenge, $origin);
        $authData = $this->buildAuthenticatorData(
            rpId: $rpId,
            flags: $this->flagsByte(userPresent: true, userVerified: $userVerified, attestedCredential: true),
            signCount: 1,
            attestedCredentialData: $this->buildAttestedCredentialData($credentialId, $x, $y),
        );

        $attestationObject = $this->buildAttestationObject($authData);

        return [
            'id' => self::base64UrlEncode($credentialId),
            'type' => 'public-key',
            'rawId' => self::base64UrlEncode($credentialId),
            'response' => [
                'clientDataJSON' => self::base64UrlEncode($clientDataJson),
                'attestationObject' => self::base64UrlEncode($attestationObject),
                'transports' => [],
            ],
            'clientExtensionResults' => new \stdClass(),
        ];
    }

    /** @return array<string, mixed> shape produced by passkey.js encodeAssertionCredential() */
    public function createLoginResponse(
        array $options,
        string $credentialIdBase64Url,
        string $origin,
        bool $userVerified = true,
    ): array {
        $rpId = (string) ($options['rpId'] ?? parse_url($origin, PHP_URL_HOST));
        $challenge = self::base64UrlDecode((string) $options['challenge']);
        $credentialId = self::base64UrlDecode($credentialIdBase64Url);

        if (!isset(self::$credentials[$credentialId])) {
            throw new \RuntimeException(sprintf('Unknown credential id "%s" — register it first.', $credentialIdBase64Url));
        }

        $entry = &self::$credentials[$credentialId];
        ++$entry['signCount'];

        $clientDataJson = $this->buildClientDataJson('webauthn.get', $challenge, $origin);
        $authData = $this->buildAuthenticatorData(
            rpId: $rpId,
            flags: $this->flagsByte(userPresent: true, userVerified: $userVerified, attestedCredential: false),
            signCount: $entry['signCount'],
            attestedCredentialData: null,
        );

        $signature = $this->signEs256($authData . hash('sha256', $clientDataJson, true), $entry['privateKeyPem']);

        return [
            'id' => $credentialIdBase64Url,
            'type' => 'public-key',
            'rawId' => $credentialIdBase64Url,
            'response' => [
                'clientDataJSON' => self::base64UrlEncode($clientDataJson),
                'authenticatorData' => self::base64UrlEncode($authData),
                'signature' => self::base64UrlEncode($signature),
                'userHandle' => null,
            ],
            'clientExtensionResults' => new \stdClass(),
        ];
    }

    public static function reset(): void
    {
        self::$credentials = [];
    }

    /** @return array{privateKeyPem: string, publicKeyX: string, publicKeyY: string} */
    protected function generateKeypair(): array
    {
        $key = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if ($key === false) {
            throw new \RuntimeException('Failed to generate EC keypair: ' . (string) openssl_error_string());
        }

        if (!openssl_pkey_export($key, $privateKeyPem)) {
            throw new \RuntimeException('Failed to export private key.');
        }

        $details = openssl_pkey_get_details($key);
        if ($details === false || !isset($details['ec']['x'], $details['ec']['y'])) {
            throw new \RuntimeException('Failed to read EC key details.');
        }

        return [
            'privateKeyPem' => $privateKeyPem,
            'publicKeyX' => $this->padCoordinate((string) $details['ec']['x']),
            'publicKeyY' => $this->padCoordinate((string) $details['ec']['y']),
        ];
    }

    protected function padCoordinate(string $coord): string
    {
        // P-256 coordinates are 32 bytes; openssl may strip leading zero bytes.
        if (strlen($coord) >= 32) {
            return substr($coord, -32);
        }

        return str_pad($coord, 32, "\x00", STR_PAD_LEFT);
    }

    protected function buildClientDataJson(string $type, string $challenge, string $origin): string
    {
        // Order of keys matches what real browsers emit and what
        // web-auth/webauthn-lib accepts. crossOrigin is mandatory for "create".
        $payload = [
            'type' => $type,
            'challenge' => self::base64UrlEncode($challenge),
            'origin' => $origin,
            'crossOrigin' => false,
        ];

        return (string) json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    protected function flagsByte(bool $userPresent, bool $userVerified, bool $attestedCredential): int
    {
        $flags = 0;
        if ($userPresent) {
            $flags |= 0x01;
        }
        if ($userVerified) {
            $flags |= 0x04;
        }
        if ($attestedCredential) {
            $flags |= 0x40;
        }

        return $flags;
    }

    protected function buildAuthenticatorData(
        string $rpId,
        int $flags,
        int $signCount,
        ?string $attestedCredentialData,
    ): string {
        $rpIdHash = hash('sha256', $rpId, true);
        $signCountBytes = pack('N', $signCount);

        return $rpIdHash . chr($flags) . $signCountBytes . ($attestedCredentialData ?? '');
    }

    protected function buildAttestedCredentialData(string $credentialId, string $publicKeyX, string $publicKeyY): string
    {
        $aaguid = str_repeat("\x00", 16);
        $credentialIdLength = pack('n', strlen($credentialId));
        $cosePublicKey = $this->buildCosePublicKey($publicKeyX, $publicKeyY);

        return $aaguid . $credentialIdLength . $credentialId . $cosePublicKey;
    }

    protected function buildCosePublicKey(string $x, string $y): string
    {
        // COSE_Key for ES256 (RFC 8152 §13.1.1):
        //   1 (kty)  = 2 (EC2)
        //   3 (alg)  = -7 (ES256)
        //  -1 (crv)  = 1 (P-256)
        //  -2 (x)    = 32-byte big-endian
        //  -3 (y)    = 32-byte big-endian
        $map = MapObject::create()
            ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))
            ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(-7))
            ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))
            ->add(NegativeIntegerObject::create(-2), ByteStringObject::create($x))
            ->add(NegativeIntegerObject::create(-3), ByteStringObject::create($y))
        ;

        return (string) $map;
    }

    protected function buildAttestationObject(string $authData): string
    {
        $map = MapObject::create()
            ->add(TextStringObject::create('fmt'), TextStringObject::create('none'))
            ->add(TextStringObject::create('attStmt'), MapObject::create())
            ->add(TextStringObject::create('authData'), ByteStringObject::create($authData))
        ;

        return (string) $map;
    }

    protected function signEs256(string $data, string $privateKeyPem): string
    {
        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false) {
            throw new \RuntimeException('Failed to load private key.');
        }

        if (!openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Failed to sign data.');
        }

        // openssl_sign returns DER-encoded ECDSA signature for EC keys, which
        // is what web-auth/webauthn-lib expects for ES256 verification.
        return $signature;
    }

    public static function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $value): string
    {
        $padded = str_pad(strtr($value, '-_', '+/'), strlen($value) + (4 - strlen($value) % 4) % 4, '=');
        $decoded = base64_decode($padded, true);
        if ($decoded === false) {
            throw new \RuntimeException(sprintf('Invalid base64url value "%s".', $value));
        }

        return $decoded;
    }
}
