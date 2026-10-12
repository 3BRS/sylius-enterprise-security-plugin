<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractPasskeyRegistrationVerifyController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\CustomerPasskeyRegistrationVerifierInterface;

class PasskeyRegistrationVerifyController extends AbstractPasskeyRegistrationVerifyController implements PasskeyRegistrationVerifyControllerInterface
{
    public function __construct(
        protected CustomerPasskeyRegistrationVerifierInterface $verifier,
        protected EntityManagerInterface $entityManager,
        TokenStorageInterface $tokenStorage,
        LoggerInterface $logger,
        bool $enabled,
    ) {
        parent::__construct($tokenStorage, $logger, $enabled);
    }

    protected function isAcceptableUser(UserInterface $user): bool
    {
        return $user instanceof ShopUserInterface;
    }

    protected function verifyAndPersist(UserInterface $user, string $credentialJson, string $label, string $host): void
    {
        \assert($user instanceof ShopUserInterface);

        $credential = $this->verifier->verifyAndCreate($user, $credentialJson, $label, $host);
        $this->entityManager->persist($credential);
        $this->entityManager->flush();
    }

    protected function getLogChannel(): string
    {
        return 'three_brs.passkey.shop';
    }
}
