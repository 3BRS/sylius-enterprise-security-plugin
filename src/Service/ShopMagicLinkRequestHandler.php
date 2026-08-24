<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use ThreeBRS\EnterpriseSecurityBundle\MagicLink\MagicLinkTokenGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\EnterpriseSecurityBundle\Timing\TimingPaddingInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerMagicLinkToken;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\CustomerMagicLinkEmailManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SecuritySettingsBounds;

class ShopMagicLinkRequestHandler implements ShopMagicLinkRequestHandlerInterface
{
    /**
     * @param CustomerRepositoryInterface<CustomerInterface> $customerRepository
     */
    public function __construct(
        protected CustomerRepositoryInterface $customerRepository,
        protected MagicLinkTokenGeneratorInterface $tokenGenerator,
        protected CustomerMagicLinkEmailManagerInterface $emailManager,
        protected EntityManagerInterface $entityManager,
        protected ClockInterface $clock,
        protected TimingPaddingInterface $timingPadding,
        protected bool $enabled,
        protected SettingsProviderInterface $settings,
    ) {
    }

    public function request(string $email): void
    {
        if (!$this->enabled) {
            return;
        }

        // Pad every code path to the same wall-clock deadline so an attacker
        // cannot tell whether the email exists, was rate-limited, or got the
        // full happy path (DB write + SMTP send) by measuring response time.
        $startedAt = microtime(true);

        try {
            $plainToken = $this->tokenGenerator->generatePlainToken();
            $tokenHash = $this->tokenGenerator->hash($plainToken);
            $now = $this->clock->now();

            $user = $this->findUserByEmail($email);
            if ($user === null) {
                return;
            }

            $token = new CustomerMagicLinkToken();
            $token->setShopUser($user);
            $token->setTokenHash($tokenHash);
            $expirationSeconds = $this->getExpirationSeconds();
            $token->setExpiresAt($now->add(new \DateInterval('PT' . $expirationSeconds . 'S')));

            $this->entityManager->persist($token);
            $this->entityManager->flush();

            $this->emailManager->sendMagicLink($user, $plainToken, $expirationSeconds);
        } finally {
            $this->timingPadding->padTo($startedAt);
        }
    }

    protected function findUserByEmail(string $email): ?ShopUserInterface
    {
        if ($email === '') {
            return null;
        }

        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        if (!$customer instanceof CustomerInterface) {
            return null;
        }

        $user = $customer->getUser();

        return $user instanceof ShopUserInterface ? $user : null;
    }

    /**
     * The lifetime is what an administrator sets in Security Settings, not what the
     * container was built with — the field was editable, saved and read back into the
     * form, while the link kept expiring on the configuration file's value.
     *
     * A value from outside the range the form accepts can only come from something
     * that wrote the row directly, and a link that never expires is the wrong way to
     * fail, so it is clamped rather than trusted.
     */
    protected function getExpirationSeconds(): int
    {
        $seconds = $this->settings->getInt('magic_link.expiration_seconds', SettingsScope::CUSTOMER);

        return max(
            SecuritySettingsBounds::MAGIC_LINK_EXPIRATION_SECONDS_MIN,
            min(SecuritySettingsBounds::MAGIC_LINK_EXPIRATION_SECONDS_MAX, $seconds),
        );
    }
}
