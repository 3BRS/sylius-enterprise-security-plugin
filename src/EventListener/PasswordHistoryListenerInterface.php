<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;

interface PasswordHistoryListenerInterface
{
    public function onFlush(OnFlushEventArgs $args): void;

    public function postFlush(PostFlushEventArgs $args): void;
}
