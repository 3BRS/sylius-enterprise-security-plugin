<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service\Passkey;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Uid\Uuid;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\CustomerPasskeyRegistrationVerifier;
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

#[CoversClass(CustomerPasskeyRegistrationVerifier::class)]
class CustomerPasskeyRegistrationVerifierTest extends TestCase
{
    public function testThrowsWhenNoSessionOptionsStored(): void
    {
        $storage = $this->createStub(SessionPasskeyOptionsStorageInterface::class);
        $storage->method('consume')->willReturn(null);

        $verifier = new CustomerPasskeyRegistrationVerifier(
            $storage,
            $this->createStub(PasskeyWebauthnSerializerInterface::class),
            $this->createStub(PasskeyValidatorFactoryInterface::class),
        );

        $this->expectException(\RuntimeException::class);
        $verifier->verifyAndCreate(
            $this->createStub(ShopUserInterface::class),
            '{}',
            'My Passkey',
            'shop.example.com',
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
                    PublicKeyCredentialRpEntity::create('Test', 'shop.example.com'),
                    PublicKeyCredentialUserEntity::create('test', 'user-id', 'test'),
                    'challenge-bytes',
                );
            }

            return new PublicKeyCredential('public-key', 'raw-id', $this->createStub(AuthenticatorAssertionResponse::class));
        });

        $verifier = new CustomerPasskeyRegistrationVerifier(
            $storage,
            $serializer,
            $this->createStub(PasskeyValidatorFactoryInterface::class),
        );

        $this->expectException(\RuntimeException::class);
        $verifier->verifyAndCreate(
            $this->createStub(ShopUserInterface::class),
            '{}',
            'My Passkey',
            'shop.example.com',
        );
    }

    public function testHappyPathReturnsCredentialEntityWithFieldsFromValidator(): void
    {
        $storage = $this->createStub(SessionPasskeyOptionsStorageInterface::class);
        $storage->method('consume')->willReturn('serialized-options');

        $aaguid = Uuid::v4();
        $source = new PublicKeyCredentialSource(
            publicKeyCredentialId: 'credential-id-bytes',
            type: 'public-key',
            transports: ['internal'],
            attestationType: 'none',
            trustPath: EmptyTrustPath::create(),
            aaguid: $aaguid,
            credentialPublicKey: 'cose-key-bytes',
            userHandle: 'user-handle',
            counter: 0,
        );

        $attestationResponse = $this->createStub(AuthenticatorAttestationResponse::class);
        $publicKeyCredential = new PublicKeyCredential('public-key', 'raw-id', $attestationResponse);

        $serializer = $this->createStub(PasskeyWebauthnSerializerInterface::class);
        $serializer->method('deserialize')->willReturnCallback(function (string $payload, string $type) use ($publicKeyCredential) {
            if ($type === PublicKeyCredentialCreationOptions::class) {
                return PublicKeyCredentialCreationOptions::create(
                    PublicKeyCredentialRpEntity::create('Test', 'shop.example.com'),
                    PublicKeyCredentialUserEntity::create('test', 'user-id', 'test'),
                    'challenge-bytes',
                );
            }

            return $publicKeyCredential;
        });
        $serializer->method('normalize')->willReturn(['source' => 'normalized']);

        $validator = $this->createMock(AuthenticatorAttestationResponseValidator::class);
        $validator->expects(self::once())->method('check')->willReturn($source);

        $validatorFactory = $this->createStub(PasskeyValidatorFactoryInterface::class);
        $validatorFactory->method('createAttestationValidator')->willReturn($validator);

        $verifier = new CustomerPasskeyRegistrationVerifier($storage, $serializer, $validatorFactory);

        $shopUser = $this->createStub(ShopUserInterface::class);

        $credential = $verifier->verifyAndCreate($shopUser, '{}', 'My Passkey', 'shop.example.com');

        self::assertSame($shopUser, $credential->getShopUser());
        self::assertSame('credential-id-bytes', $credential->getCredentialId());
        self::assertSame(['source' => 'normalized'], $credential->getCredentialSource());
        self::assertSame('My Passkey', $credential->getLabel());
    }
}
