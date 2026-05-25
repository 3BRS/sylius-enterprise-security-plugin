<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\PasswordChangeEmailManagerInterface;

class PasswordChangeNotificationListener implements PasswordChangeNotificationListenerInterface
{
    protected const SELF_INITIATED_ROUTES = [
        'sylius_shop_password_reset',
        'sylius_admin_password_reset',
        'sylius_api_shop_customer_patch_reset_password',
        'sylius_api_admin_reset_password_patch',
    ];

    /** @var array<int, ShopUserInterface|AdminUserInterface> */
    protected array $pendingNotifications = [];

    protected LoggerInterface $logger;

    public function __construct(
        protected PasswordChangeEmailManagerInterface $emailManager,
        protected RequestStack $requestStack,
        protected TokenStorageInterface $tokenStorage,
        protected SettingsProviderInterface $settings,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $customerEnabled = $this->settings->getBool('password_change_notification.enabled', SettingsScope::CUSTOMER);
        $adminEnabled = $this->settings->getBool('password_change_notification.enabled', SettingsScope::ADMIN);

        if (!$customerEnabled && !$adminEnabled) {
            return;
        }

        $uow = $args->getObjectManager()->getUnitOfWork();

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof ShopUserInterface && !$entity instanceof AdminUserInterface) {
                continue;
            }

            if ($entity instanceof ShopUserInterface && !$customerEnabled) {
                continue;
            }

            if ($entity instanceof AdminUserInterface && !$adminEnabled) {
                continue;
            }

            if (!array_key_exists('password', $uow->getEntityChangeSet($entity))) {
                continue;
            }

            $this->pendingNotifications[spl_object_id($entity)] = $entity;
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->pendingNotifications === []) {
            return;
        }

        $pending = $this->pendingNotifications;
        $this->pendingNotifications = [];

        $request = $this->requestStack->getCurrentRequest();
        $currentUser = $this->tokenStorage->getToken()?->getUser();
        $selfInitiatedRoute = $this->isSelfInitiatedRoute($request);

        foreach ($pending as $user) {
            $initiatedByUser = $this->isInitiatedByUser($currentUser, $user, $selfInitiatedRoute);

            try {
                $this->emailManager->sendPasswordChangedEmail($user, $request, $initiatedByUser);
            } catch (\Exception $exception) {
                $this->logger->error('Failed to send password change notification email.', [
                    'exception' => $exception,
                    'user' => $user->getUserIdentifier(),
                    'userType' => $user instanceof ShopUserInterface ? 'customer' : 'admin',
                    'route' => $request?->attributes->get('_route'),
                ]);
            }
        }
    }

    protected function isInitiatedByUser(?object $currentUser, ShopUserInterface|AdminUserInterface $target, bool $selfInitiatedRoute): bool
    {
        if ($selfInitiatedRoute) {
            return true;
        }

        if (!$currentUser instanceof UserInterface) {
            return false;
        }

        return $currentUser->getUserIdentifier() === $target->getUserIdentifier();
    }

    protected function isSelfInitiatedRoute(?Request $request): bool
    {
        if ($request === null) {
            return false;
        }

        $route = $request->attributes->get('_route');

        return is_string($route) && in_array($route, static::SELF_INITIATED_ROUTES, true);
    }
}
