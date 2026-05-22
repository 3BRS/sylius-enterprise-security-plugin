<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Lockout;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Lockout\AbstractLockoutManager;
use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockableUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockoutPolicyInterface;
use ThreeBRS\EnterpriseSecurityBundle\RateLimit\RateLimitGuardInterface;

class AdminUserLockoutManager extends AbstractLockoutManager implements AdminUserLockoutManagerInterface
{
    public function __construct(
        LockoutPolicyInterface $policy,
        protected EntityManagerInterface $entityManager,
        ClockInterface $clock,
        protected RateLimitGuardInterface $rateLimitGuard,
    ) {
        parent::__construct($policy, $clock);
    }

    protected function withPessimisticLock(LockableUserInterface $user, \Closure $callback): void
    {
        $this->entityManager->beginTransaction();

        try {
            $this->entityManager->lock($user, LockMode::PESSIMISTIC_WRITE);
            $this->entityManager->refresh($user);

            $callback();

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $exception) {
            $this->entityManager->rollback();

            throw $exception;
        }
    }

    protected function commit(): void
    {
        $this->entityManager->flush();
    }

    protected function clearRateLimitForUser(LockableUserInterface $user): void
    {
        if (!$user instanceof AdminUserInterface) {
            return;
        }

        $username = $user->getUsername();
        if ($username !== null && $username !== '') {
            $this->rateLimitGuard->reset('admin', 'login', $username);
        }

        $email = $user->getEmail();
        if ($email !== null && $email !== '' && $email !== $username) {
            $this->rateLimitGuard->reset('admin', 'login', $email);
        }
    }
}
