<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSocialAccountLink;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\OAuth\OAuthUserInfoInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSocialAccountLinkRepositoryInterface;

class AdminSocialLoginHandler implements AdminSocialLoginHandlerInterface
{
    /**
     * @param UserRepositoryInterface<AdminUserInterface> $adminUserRepository
     * @param FactoryInterface<AdminUserInterface>        $adminUserFactory
     * @param list<string>                                $allowedEmailDomains
     */
    public function __construct(
        private UserRepositoryInterface $adminUserRepository,
        private FactoryInterface $adminUserFactory,
        private AdminUserSocialAccountLinkRepositoryInterface $linkRepository,
        private EntityManagerInterface $entityManager,
        private array $allowedEmailDomains,
        private string $defaultLocale,
    ) {
    }

    public function findExistingLinkUser(OAuthUserInfoInterface $info): ?AdminUserInterface
    {
        $link = $this->linkRepository->findByProviderAndProviderUserId($info->getProvider(), $info->getProviderUserId());

        return $link?->getAdminUser();
    }

    public function findUserByEmail(string $email): ?AdminUserInterface
    {
        $user = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower($email)]);

        return $user instanceof AdminUserInterface ? $user : null;
    }

    public function canAutoRegister(OAuthUserInfoInterface $info): bool
    {
        $email = $info->getEmail();
        if ($email === null || $email === '') {
            return false;
        }

        if ($info->isEmailVerified() === false) {
            return false;
        }

        if ($this->allowedEmailDomains === []) {
            return false;
        }

        $atPos = strrpos($email, '@');
        if ($atPos === false) {
            return false;
        }

        $domain = strtolower(substr($email, $atPos + 1));
        $normalized = array_map('strtolower', $this->allowedEmailDomains);

        return in_array($domain, $normalized, true);
    }

    /** @internal Callers must gate with {@see canAutoRegister()} before invoking. */
    public function registerAndLink(OAuthUserInfoInterface $info): AdminUserInterface
    {
        $email = $info->getEmail();
        if ($email === null || $email === '') {
            throw new \LogicException('Cannot register an admin user without an email address from the OAuth provider.');
        }

        /** @var AdminUserInterface $adminUser */
        $adminUser = $this->adminUserFactory->createNew();
        $adminUser->setEmail($email);
        $adminUser->setUsername($email);
        $adminUser->setFirstName($info->getFirstName());
        $adminUser->setLastName($info->getLastName());
        $adminUser->setEnabled(true);
        $adminUser->setLocaleCode($this->defaultLocale);
        $adminUser->addRole('ROLE_ADMINISTRATION_ACCESS');

        $this->entityManager->persist($adminUser);

        $link = $this->createLink($adminUser, $info);
        $link->setLastUsedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $adminUser;
    }

    public function linkExistingUser(AdminUserInterface $user, OAuthUserInfoInterface $info): void
    {
        $this->createLink($user, $info);
        $this->entityManager->flush();
    }

    public function touchLastUsed(AdminUserInterface $user, OAuthUserInfoInterface $info): void
    {
        $link = $this->linkRepository->findOneByAdminUserAndProvider($user, $info->getProvider());
        if ($link === null) {
            return;
        }

        $link->setLastUsedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    private function createLink(AdminUserInterface $user, OAuthUserInfoInterface $info): AdminUserSocialAccountLink
    {
        $link = new AdminUserSocialAccountLink();
        $link->setAdminUser($user);
        $link->setProvider($info->getProvider());
        $link->setProviderUserId($info->getProviderUserId());
        $link->setEmail($info->getEmail());

        $this->entityManager->persist($link);

        return $link;
    }
}
