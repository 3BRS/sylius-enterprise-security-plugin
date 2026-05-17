<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyWebauthnSerializerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\AdminPasskeyRegistrationOptionsBuilderInterface;

class PasskeyRegistrationOptionsController implements PasskeyRegistrationOptionsControllerInterface
{
    public function __construct(
        protected AdminPasskeyRegistrationOptionsBuilderInterface $optionsBuilder,
        protected PasskeyWebauthnSerializerInterface $serializer,
        protected TokenStorageInterface $tokenStorage,
        protected bool $enabled,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException();
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        if (!$user instanceof AdminUserInterface) {
            throw new AccessDeniedHttpException();
        }

        $options = $this->optionsBuilder->build($user);

        return JsonResponse::fromJsonString($this->serializer->serialize($options));
    }
}
