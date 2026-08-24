<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\AbstractPasskeyAssertionVerifier;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyCredentialRecordInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyValidatorFactoryInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyWebauthnSerializerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\SessionPasskeyOptionsStorageInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerPasskeyCredentialInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerPasskeyCredentialRepositoryInterface;

class CustomerPasskeyAssertionVerifier extends AbstractPasskeyAssertionVerifier implements CustomerPasskeyAssertionVerifierInterface
{
    public function __construct(
        CustomerPasskeyCredentialRepositoryInterface $credentialRepository,
        protected EntityManagerInterface $entityManager,
        SessionPasskeyOptionsStorageInterface $sessionStorage,
        PasskeyWebauthnSerializerInterface $serializer,
        PasskeyValidatorFactoryInterface $validatorFactory,
        ClockInterface $clock,
    ) {
        parent::__construct($credentialRepository, $sessionStorage, $serializer, $validatorFactory, $clock);
    }

    public function verify(string $credentialResponseJson, string $host): CustomerPasskeyAssertionResultInterface
    {
        $result = parent::verify($credentialResponseJson, $host);
        \assert($result instanceof CustomerPasskeyAssertionResultInterface);

        return $result;
    }

    protected function getOptionsSessionKey(): string
    {
        return CustomerPasskeyAssertionOptionsBuilder::SESSION_KEY;
    }

    protected function resolveUser(PasskeyCredentialRecordInterface $credential): UserInterface
    {
        \assert($credential instanceof CustomerPasskeyCredentialInterface);

        return $credential->getShopUser();
    }

    protected function createResult(UserInterface $user): CustomerPasskeyAssertionResultInterface
    {
        \assert($user instanceof ShopUserInterface);

        return new CustomerPasskeyAssertionResult($user);
    }

    protected function commit(): void
    {
        $this->entityManager->flush();
    }
}
