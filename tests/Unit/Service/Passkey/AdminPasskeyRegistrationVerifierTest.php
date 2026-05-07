<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service\Passkey;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Uid\Uuid;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\AdminPasskeyRegistrationVerifier;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\SessionPasskeyOptionsStorageInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\PasskeyValidatorFactoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\PasskeyWebauthnSerializerInterface;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;

#[CoversClass(AdminPasskeyRegistrationVerifier::class)]
class AdminPasskeyRegistrationVerifierTest extends TestCase
{
    public function testThrowsWhenNoSessionOptionsStored(): void
    {
        $storage = $this->createStub(SessionPasskeyOptionsStorageInterface::class);
        $storage->method('consume')->willReturn(null);

        $verifier = new AdminPasskeyRegistrationVerifier(
            $storage,
            $this->createStub(PasskeyWebauthnSerializerInterface::class),
            $this->createStub(PasskeyValidatorFactoryInterface::class),
        );

        $this->expectException(\RuntimeException::class);
        $verifier->verifyAndCreate(
            $this->createStub(AdminUserInterface::class),
            '{}',
            'My Passkey',
            'admin.example.com',
        );
    }

    public function testThrowsWhenResponseIsNotAttestation(): void
    {
        $storage = $this->createStub(SessionPasskeyOptionsStorageInterface::class);
        $storage->method('consume')->willReturn('serialized-options');

        $serializer = $this->createStub(PasskeyWebauthnSerializerInterface::class);
        $serializer->method('deserialize')->willReturnCallback(function (string $payload, string $type) {
            if ($type === PublicKeyCredentialCreationOptions::class) {
                return PublicKeyCredentialCreationOptions::create(
                    PublicKeyCredentialRpEntity::create('Test', 'admin.example.com'),
                    PublicKeyCredentialUserEntity::create('admin', 'admin-id', 'admin'),
                    'challenge-bytes',
                );
            }

            return new PublicKeyCredential('public-key', 'raw-id', $this->createStub(AuthenticatorAssertionResponse::class));
        });

        $verifier = new AdminPasskeyRegistrationVerifier(
            $storage,
            $serializer,
            $this->createStub(PasskeyValidatorFactoryInterface::class),
        );

        $this->expectException(\RuntimeException::class);
        $verifier->verifyAndCreate(
            $this->createStub(AdminUserInterface::class),
            '{}',
            'My Passkey',
            'admin.example.com',
        );
    }

    public function testHappyPathReturnsCredentialEntityWithFieldsFromValidator(): void
    {
        $storage = $this->createStub(SessionPasskeyOptionsStorageInterface::class);
        $storage->method('consume')->willReturn('serialized-options');

        $aaguid = Uuid::v4();
        $source = new PublicKeyCredentialSource(
            publicKeyCredentialId: 'admin-credential-id',
            type: 'public-key',
            transports: ['usb'],
            attestationType: 'none',
            trustPath: EmptyTrustPath::create(),
            aaguid: $aaguid,
            credentialPublicKey: 'cose-key-bytes',
            userHandle: 'admin-handle',
            counter: 0,
        );

        $attestationResponse = $this->createStub(AuthenticatorAttestationResponse::class);
        $publicKeyCredential = new PublicKeyCredential('public-key', 'raw-id', $attestationResponse);

        $serializer = $this->createStub(PasskeyWebauthnSerializerInterface::class);
        $serializer->method('deserialize')->willReturnCallback(function (string $payload, string $type) use ($publicKeyCredential) {
            if ($type === PublicKeyCredentialCreationOptions::class) {
                return PublicKeyCredentialCreationOptions::create(
                    PublicKeyCredentialRpEntity::create('Test', 'admin.example.com'),
                    PublicKeyCredentialUserEntity::create('admin', 'admin-id', 'admin'),
                    'challenge-bytes',
                );
            }

            return $publicKeyCredential;
        });
        $serializer->method('normalize')->willReturn(['source' => 'admin-normalized']);

        $validator = $this->createMock(AuthenticatorAttestationResponseValidator::class);
        $validator->expects(self::once())->method('check')->willReturn($source);

        $validatorFactory = $this->createStub(PasskeyValidatorFactoryInterface::class);
        $validatorFactory->method('createAttestationValidator')->willReturn($validator);

        $verifier = new AdminPasskeyRegistrationVerifier($storage, $serializer, $validatorFactory);

        $adminUser = $this->createStub(AdminUserInterface::class);

        $credential = $verifier->verifyAndCreate($adminUser, '{}', 'YubiKey', 'admin.example.com');

        self::assertSame($adminUser, $credential->getAdminUser());
        self::assertSame('admin-credential-id', $credential->getCredentialId());
        self::assertSame(['source' => 'admin-normalized'], $credential->getCredentialSource());
        self::assertSame('YubiKey', $credential->getLabel());
    }
}
