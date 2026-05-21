<?php

declare(strict_types=1);

namespace ThreeBRS\EnterpriseSecurityBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

abstract class AbstractLockedUsersListController
{
    public function __construct(
        protected Environment $twig,
        protected bool $enabled,
    ) {
    }

    public function __invoke(): Response
    {
        if (! $this->enabled) {
            throw new NotFoundHttpException();
        }

        return new Response($this->twig->render($this->getTemplate(), [
            'users' => $this->findAllLockedUsers(),
        ]));
    }

    /**
     * @return iterable<object>
     */
    abstract protected function findAllLockedUsers(): iterable;

    abstract protected function getTemplate(): string;
}
