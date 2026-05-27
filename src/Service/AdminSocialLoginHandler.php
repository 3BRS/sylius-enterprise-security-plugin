<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\AutoRegistrationPolicyInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthUserInfoInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSocialAccountLink;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSocialAccountLinkRepositoryInterface;

class AdminSocialLoginHandler implements AdminSocialLoginHandlerInterface
{
    /**
     * @param UserRepositoryInterface<AdminUserInterface> $adminUserRepository
     * @param FactoryInterface<AdminUserInterface>        $adminUserFactory
     */
    public function __construct(
        protected UserRepositoryInterface $adminUserRepository,
        protected FactoryInterface $adminUserFactory,
        protected AdminUserSocialAccountLinkRepositoryInterface $linkRepository,
        protected EntityManagerInterface $entityManager,
        protected SettingsProviderInterface $settings,
        protected AutoRegistrationPolicyInterface $autoRegistrationPolicy,
    ) {
    }

    public function findExistingLinkUser(OAuthUserInfoInterface $info): ?AdminUserInterface
    {
        $link = $this->linkRepository->findByProviderAndProviderUserId($info->getProvider(), $info->getProviderUserId());

        return $link?->getAdminUser();
    }

    public function findUserByEmail(string $email): ?AdminUserInterface
    {
        $user = $this->adminUserRepository->findOneByEmail(strtolower($email));

        return $user instanceof AdminUserInterface ? $user : null;
    }

    public function canAutoRegister(OAuthUserInfoInterface $info): bool
    {
        // Admin-scope semantics: empty whitelist = strict (no auto-registration).
        // The bundle's AutoRegistrationPolicy treats `[]` as "no domain matches"
        // → returns false. Counterpart in ShopSocialLoginHandler maps `[]` to
        // `null` for the opposite ("no restriction") customer-scope default.
        return $this->autoRegistrationPolicy->canAutoRegister($info, $this->loadAllowedEmailDomains());
    }

    /** @return list<string> */
    protected function loadAllowedEmailDomains(): array
    {
        $value = $this->settings->get('oauth.auto_register_allowed_email_domains', SettingsScope::ADMIN);
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $domain) {
            if (is_string($domain) && $domain !== '') {
                $result[] = $domain;
            }
        }

        return $result;
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
        $adminUser->setLocaleCode($this->settings->getString('oauth.default_locale', SettingsScope::ADMIN));
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

    protected function createLink(AdminUserInterface $user, OAuthUserInfoInterface $info): AdminUserSocialAccountLink
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
