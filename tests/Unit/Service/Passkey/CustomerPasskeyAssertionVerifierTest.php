<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service\Passkey;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Uid\Uuid;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerPasskeyCredentialInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerPasskeyCredentialRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\CustomerPasskeyAssertionVerifier;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\PasskeyOptionsSessionStorageInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\PasskeyValidatorFactoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\PasskeyWebauthnSerializerInterface;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorData;
use Webauthn\CollectedClientData;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\TrustPath\EmptyTrustPath;

#[CoversClass(CustomerPasskeyAssertionVerifier::class)]
class CustomerPasskeyAssertionVerifierTest extends TestCase
{
    public function testThrowsWhenNoSessionOptionsStored(): void
    {
        $storage = $this->createStub(PasskeyOptionsSessionStorageInterface::class);
        $storage->method('consume')->willReturn(null);

        $verifier = new CustomerPasskeyAssertionVerifier(
            $this->createStub(CustomerPasskeyCredentialRepositoryInterface::class),
            $this->createStub(EntityManagerInterface::class),
            $storage,
            $this->createStub(PasskeyWebauthnSerializerInterface::class),
            $this->createStub(PasskeyValidatorFactoryInterface::class),
            $this->createStub(ClockInterface::class),
        );

        $this->expectException(\RuntimeException::class);
        $verifier->verify('{}', 'shop.example.com');
    }

    public function testThrowsWhenResponseIsNotAssertion(): void
    {
        $storage = $this->createStub(PasskeyOptionsSessionStorageInterface::class);
        $storage->method('consume')->willReturn('serialized-options');

        $serializer = $this->createStub(PasskeyWebauthnSerializerInterface::class);
        $serializer->method('deserialize')->willReturnCallback(function (string $payload, string $type) {
            if ($type === PublicKeyCredentialRequestOptions::class) {
                return PublicKeyCredentialRequestOptions::create('challenge-bytes');
            }

            return new PublicKeyCredential('public-key', 'raw-id', $this->createStub(AuthenticatorAttestationResponse::class));
        });

        $verifier = new CustomerPasskeyAssertionVerifier(
            $this->createStub(CustomerPasskeyCredentialRepositoryInterface::class),
            $this->createStub(EntityManagerInterface::class),
            $storage,
            $serializer,
            $this->createStub(PasskeyValidatorFactoryInterface::class),
            $this->createStub(ClockInterface::class),
        );

        $this->expectException(\RuntimeException::class);
        $verifier->verify('{}', 'shop.example.com');
    }

    public function testThrowsWhenCredentialNotFound(): void
    {
        $storage = $this->createStub(PasskeyOptionsSessionStorageInterface::class);
        $storage->method('consume')->willReturn('serialized-options');

        $assertion = $this->createStub(AuthenticatorAssertionResponse::class);
        $publicKeyCredential = new PublicKeyCredential('public-key', 'unknown-id', $assertion);

        $serializer = $this->createStub(PasskeyWebauthnSerializerInterface::class);
        $serializer->method('deserialize')->willReturnCallback(function (string $payload, string $type) use ($publicKeyCredential) {
            if ($type === PublicKeyCredentialRequestOptions::class) {
                return PublicKeyCredentialRequestOptions::create('challenge-bytes');
            }

            return $publicKeyCredential;
        });

        $repo = $this->createMock(CustomerPasskeyCredentialRepositoryInterface::class);
        $repo->expects(self::once())->method('findOneByCredentialId')->with('unknown-id')->willReturn(null);

        $verifier = new CustomerPasskeyAssertionVerifier(
            $repo,
            $this->createStub(EntityManagerInterface::class),
            $storage,
            $serializer,
            $this->createStub(PasskeyValidatorFactoryInterface::class),
            $this->createStub(ClockInterface::class),
        );

        $this->expectException(\RuntimeException::class);
        $verifier->verify('{}', 'shop.example.com');
    }

    public function testHappyPathReturnsResultAndUpdatesEntity(): void
    {
        $storage = $this->createStub(PasskeyOptionsSessionStorageInterface::class);
        $storage->method('consume')->willReturn('serialized-options');

        $authenticatorData = $this->createStub(AuthenticatorData::class);
        $authenticatorData->method('isUserVerified')->willReturn(true);

        $clientData = $this->createStub(CollectedClientData::class);
        $assertion = new AuthenticatorAssertionResponse($clientData, $authenticatorData, 'signature-bytes', 'user-handle');

        $publicKeyCredential = new PublicKeyCredential('public-key', 'cred-id', $assertion);

        $aaguid = Uuid::v4();
        $source = new PublicKeyCredentialSource(
            publicKeyCredentialId: 'cred-id',
            type: 'public-key',
            transports: ['internal'],
            attestationType: 'none',
            trustPath: EmptyTrustPath::create(),
            aaguid: $aaguid,
            credentialPublicKey: 'cose-key-bytes',
            userHandle: 'user-handle',
            counter: 0,
        );

        $shopUser = $this->createStub(ShopUserInterface::class);
        $stored = $this->createMock(CustomerPasskeyCredentialInterface::class);
        $stored->method('getShopUser')->willReturn($shopUser);
        $stored->method('getCredentialSource')->willReturn(['stored' => true]);
        $stored->expects(self::once())->method('setCredentialSource')->with(['source' => 'normalized']);
        $stored->expects(self::once())->method('setLastUsedAt');

        $repo = $this->createMock(CustomerPasskeyCredentialRepositoryInterface::class);
        $repo->expects(self::once())->method('findOneByCredentialId')->with('cred-id')->willReturn($stored);

        $serializer = $this->createStub(PasskeyWebauthnSerializerInterface::class);
        $serializer->method('deserialize')->willReturnCallback(function (string $payload, string $type) use ($publicKeyCredential) {
            if ($type === PublicKeyCredentialRequestOptions::class) {
                return PublicKeyCredentialRequestOptions::create('challenge-bytes');
            }

            return $publicKeyCredential;
        });
        $serializer->method('denormalize')->willReturn($source);
        $serializer->method('normalize')->willReturn(['source' => 'normalized']);

        $validator = $this->createMock(AuthenticatorAssertionResponseValidator::class);
        $validator->expects(self::once())->method('check')->willReturn($source);

        $validatorFactory = $this->createStub(PasskeyValidatorFactoryInterface::class);
        $validatorFactory->method('createAssertionValidator')->willReturn($validator);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-25 10:00:00'));

        $verifier = new CustomerPasskeyAssertionVerifier($repo, $em, $storage, $serializer, $validatorFactory, $clock);

        $result = $verifier->verify('{}', 'shop.example.com');

        self::assertSame($stored, $result->getCredential());
        self::assertSame($shopUser, $result->getUser());
        self::assertTrue($result->isUserVerified());
    }
}
