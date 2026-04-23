<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\User\Model\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\PasswordChangeEmailManagerInterface;

class PasswordChangeNotificationListener implements PasswordChangeNotificationListenerInterface
{
    private const SELF_INITIATED_ROUTES = [
        'sylius_shop_password_reset',
        'sylius_admin_password_reset',
        'sylius_api_shop_customer_patch_reset_password',
        'sylius_api_admin_reset_password_patch',
        'three_brs_admin_force_password_change',
    ];

    /** @var array<int, UserInterface> */
    private array $pendingNotifications = [];

    public function __construct(
        private PasswordChangeEmailManagerInterface $emailManager,
        private RequestStack $requestStack,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof ShopUserInterface && !$entity instanceof AdminUserInterface) {
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
            $initiatedByUser = $currentUser === $user || $selfInitiatedRoute;

            if ($user instanceof ShopUserInterface) {
                $this->emailManager->sendCustomerPasswordChangedEmail($user, $request, $initiatedByUser);
            } elseif ($user instanceof AdminUserInterface) {
                $this->emailManager->sendAdminPasswordChangedEmail($user, $request, $initiatedByUser);
            }
        }
    }

    private function isSelfInitiatedRoute(?Request $request): bool
    {
        if ($request === null) {
            return false;
        }

        $route = $request->attributes->get('_route');

        return is_string($route) && in_array($route, self::SELF_INITIATED_ROUTES, true);
    }
}
