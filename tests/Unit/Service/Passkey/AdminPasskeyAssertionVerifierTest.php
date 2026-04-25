<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service\Passkey;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Uid\Uuid;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserPasskeyCredentialInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserPasskeyCredentialRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\AdminPasskeyAssertionVerifier;
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

#[CoversClass(AdminPasskeyAssertionVerifier::class)]
class AdminPasskeyAssertionVerifierTest extends TestCase
{
    public function testThrowsWhenNoSessionOptionsStored(): void
    {
        $storage = $this->createStub(PasskeyOptionsSessionStorageInterface::class);
        $storage->method('consume')->willReturn(null);

        $verifier = new AdminPasskeyAssertionVerifier(
            $this->createStub(AdminUserPasskeyCredentialRepositoryInterface::class),
            $this->createStub(EntityManagerInterface::class),
            $storage,
            $this->createStub(PasskeyWebauthnSerializerInterface::class),
            $this->createStub(PasskeyValidatorFactoryInterface::class),
            $this->createStub(ClockInterface::class),
        );

        $this->expectException(\RuntimeException::class);
        $verifier->verify('{}', 'admin.example.com');
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

        $verifier = new AdminPasskeyAssertionVerifier(
            $this->createStub(AdminUserPasskeyCredentialRepositoryInterface::class),
            $this->createStub(EntityManagerInterface::class),
            $storage,
            $serializer,
            $this->createStub(PasskeyValidatorFactoryInterface::class),
            $this->createStub(ClockInterface::class),
        );

        $this->expectException(\RuntimeException::class);
        $verifier->verify('{}', 'admin.example.com');
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

        $repo = $this->createMock(AdminUserPasskeyCredentialRepositoryInterface::class);
        $repo->expects(self::once())->method('findOneByCredentialId')->with('unknown-id')->willReturn(null);

        $verifier = new AdminPasskeyAssertionVerifier(
            $repo,
            $this->createStub(EntityManagerInterface::class),
            $storage,
            $serializer,
            $this->createStub(PasskeyValidatorFactoryInterface::class),
            $this->createStub(ClockInterface::class),
        );

        $this->expectException(\RuntimeException::class);
        $verifier->verify('{}', 'admin.example.com');
    }

    public function testHappyPathReturnsResultAndUpdatesEntity(): void
    {
        $storage = $this->createStub(PasskeyOptionsSessionStorageInterface::class);
        $storage->method('consume')->willReturn('serialized-options');

        $authenticatorData = $this->createStub(AuthenticatorData::class);
        $authenticatorData->method('isUserVerified')->willReturn(false);

        $clientData = $this->createStub(CollectedClientData::class);
        $assertion = new AuthenticatorAssertionResponse($clientData, $authenticatorData, 'signature-bytes', 'admin-handle');

        $publicKeyCredential = new PublicKeyCredential('public-key', 'admin-cred-id', $assertion);

        $aaguid = Uuid::v4();
        $source = new PublicKeyCredentialSource(
            publicKeyCredentialId: 'admin-cred-id',
            type: 'public-key',
            transports: ['usb'],
            attestationType: 'none',
            trustPath: EmptyTrustPath::create(),
            aaguid: $aaguid,
            credentialPublicKey: 'cose-key-bytes',
            userHandle: 'admin-handle',
            counter: 5,
        );

        $adminUser = $this->createStub(AdminUserInterface::class);
        $stored = $this->createMock(AdminUserPasskeyCredentialInterface::class);
        $stored->method('getAdminUser')->willReturn($adminUser);
        $stored->method('getCredentialSource')->willReturn(['stored' => true]);
        $stored->expects(self::once())->method('setCredentialSource')->with(['source' => 'admin-normalized']);
        $stored->expects(self::once())->method('setLastUsedAt');

        $repo = $this->createMock(AdminUserPasskeyCredentialRepositoryInterface::class);
        $repo->expects(self::once())->method('findOneByCredentialId')->with('admin-cred-id')->willReturn($stored);

        $serializer = $this->createStub(PasskeyWebauthnSerializerInterface::class);
        $serializer->method('deserialize')->willReturnCallback(function (string $payload, string $type) use ($publicKeyCredential) {
            if ($type === PublicKeyCredentialRequestOptions::class) {
                return PublicKeyCredentialRequestOptions::create('challenge-bytes');
            }

            return $publicKeyCredential;
        });
        $serializer->method('denormalize')->willReturn($source);
        $serializer->method('normalize')->willReturn(['source' => 'admin-normalized']);

        $validator = $this->createMock(AuthenticatorAssertionResponseValidator::class);
        $validator->expects(self::once())->method('check')->willReturn($source);

        $validatorFactory = $this->createStub(PasskeyValidatorFactoryInterface::class);
        $validatorFactory->method('createAssertionValidator')->willReturn($validator);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-25 10:00:00'));

        $verifier = new AdminPasskeyAssertionVerifier($repo, $em, $storage, $serializer, $validatorFactory, $clock);

        $result = $verifier->verify('{}', 'admin.example.com');

        self::assertSame($stored, $result->getCredential());
        self::assertSame($adminUser, $result->getUser());
        self::assertFalse($result->isUserVerified());
    }
}
