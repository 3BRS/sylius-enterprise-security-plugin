<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\AutoRegistrationPolicyInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthUserInfoInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSocialAccountLink;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSocialAccountLinkRepositoryInterface;

class ShopSocialLoginHandler implements ShopSocialLoginHandlerInterface
{
    /**
     * @param CustomerRepositoryInterface<CustomerInterface> $customerRepository
     * @param FactoryInterface<CustomerInterface>            $customerFactory
     * @param FactoryInterface<ShopUserInterface>            $shopUserFactory
     */
    public function __construct(
        protected CustomerRepositoryInterface $customerRepository,
        protected FactoryInterface $customerFactory,
        protected FactoryInterface $shopUserFactory,
        protected CustomerSocialAccountLinkRepositoryInterface $linkRepository,
        protected EntityManagerInterface $entityManager,
        protected SettingsProviderInterface $settings,
        protected AutoRegistrationPolicyInterface $autoRegistrationPolicy,
    ) {
    }

    public function findExistingLinkUser(OAuthUserInfoInterface $info): ?ShopUserInterface
    {
        $link = $this->linkRepository->findByProviderAndProviderUserId($info->getProvider(), $info->getProviderUserId());

        return $link?->getShopUser();
    }

    public function findUserByEmail(string $email): ?ShopUserInterface
    {
        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        if (!$customer instanceof CustomerInterface) {
            return null;
        }

        $user = $customer->getUser();

        return $user instanceof ShopUserInterface ? $user : null;
    }

    public function canAutoRegister(OAuthUserInfoInterface $info): bool
    {
        $domains = $this->loadAllowedEmailDomains();

        // Customer-scope semantics: empty whitelist = no restriction (allow any
        // verified email). Preserves the historical default where any verified
        // OAuth signup was accepted. Admins who want to restrict customer
        // auto-registration (e.g. to reduce bot signups) can populate the list.
        return $this->autoRegistrationPolicy->canAutoRegister($info, $domains === [] ? null : $domains);
    }

    /** @return list<string> */
    protected function loadAllowedEmailDomains(): array
    {
        $value = $this->settings->get('oauth.auto_register_allowed_email_domains', SettingsScope::CUSTOMER);
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
    public function registerAndLink(OAuthUserInfoInterface $info): ShopUserInterface
    {
        $email = $info->getEmail();
        if ($email === null || $email === '') {
            throw new \LogicException('Cannot register a shop user without an email address from the OAuth provider.');
        }

        /** @var CustomerInterface $customer */
        $customer = $this->customerFactory->createNew();
        $customer->setEmail($email);
        $customer->setFirstName($info->getFirstName());
        $customer->setLastName($info->getLastName());

        /** @var ShopUserInterface $shopUser */
        $shopUser = $this->shopUserFactory->createNew();
        $shopUser->setCustomer($customer);
        $shopUser->setEnabled(true);

        $this->entityManager->persist($customer);
        $this->entityManager->persist($shopUser);

        $link = $this->createLink($shopUser, $info);
        $link->setLastUsedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $shopUser;
    }

    public function linkExistingUser(ShopUserInterface $user, OAuthUserInfoInterface $info): void
    {
        $this->createLink($user, $info);
        $this->entityManager->flush();
    }

    public function touchLastUsed(ShopUserInterface $user, OAuthUserInfoInterface $info): void
    {
        $link = $this->linkRepository->findOneByShopUserAndProvider($user, $info->getProvider());
        if ($link === null) {
            return;
        }

        $link->setLastUsedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    protected function createLink(ShopUserInterface $user, OAuthUserInfoInterface $info): CustomerSocialAccountLink
    {
        $link = new CustomerSocialAccountLink();
        $link->setShopUser($user);
        $link->setProvider($info->getProvider());
        $link->setProviderUserId($info->getProviderUserId());
        $link->setEmail($info->getEmail());

        $this->entityManager->persist($link);

        return $link;
    }
}
