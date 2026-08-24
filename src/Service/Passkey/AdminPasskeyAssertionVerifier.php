<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\AbstractPasskeyAssertionVerifier;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyCredentialRecordInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyValidatorFactoryInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyWebauthnSerializerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\SessionPasskeyOptionsStorageInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserPasskeyCredentialInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserPasskeyCredentialRepositoryInterface;

class AdminPasskeyAssertionVerifier extends AbstractPasskeyAssertionVerifier implements AdminPasskeyAssertionVerifierInterface
{
    public function __construct(
        AdminUserPasskeyCredentialRepositoryInterface $credentialRepository,
        protected EntityManagerInterface $entityManager,
        SessionPasskeyOptionsStorageInterface $sessionStorage,
        PasskeyWebauthnSerializerInterface $serializer,
        PasskeyValidatorFactoryInterface $validatorFactory,
        ClockInterface $clock,
    ) {
        parent::__construct($credentialRepository, $sessionStorage, $serializer, $validatorFactory, $clock);
    }

    public function verify(string $credentialResponseJson, string $host): AdminPasskeyAssertionResultInterface
    {
        $result = parent::verify($credentialResponseJson, $host);
        \assert($result instanceof AdminPasskeyAssertionResultInterface);

        return $result;
    }

    protected function getOptionsSessionKey(): string
    {
        return AdminPasskeyAssertionOptionsBuilder::SESSION_KEY;
    }

    protected function resolveUser(PasskeyCredentialRecordInterface $credential): UserInterface
    {
        \assert($credential instanceof AdminUserPasskeyCredentialInterface);

        return $credential->getAdminUser();
    }

    protected function createResult(UserInterface $user): AdminPasskeyAssertionResultInterface
    {
        \assert($user instanceof AdminUserInterface);

        return new AdminPasskeyAssertionResult($user);
    }

    protected function commit(): void
    {
        $this->entityManager->flush();
    }
}
