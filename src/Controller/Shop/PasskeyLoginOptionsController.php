<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\CustomerPasskeyAssertionOptionsBuilderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\PasskeyWebauthnSerializerInterface;

class PasskeyLoginOptionsController implements PasskeyLoginOptionsControllerInterface
{
    public function __construct(
        protected CustomerPasskeyAssertionOptionsBuilderInterface $optionsBuilder,
        protected PasskeyWebauthnSerializerInterface $serializer,
        protected bool $enabled,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException();
        }

        $options = $this->optionsBuilder->build();

        return JsonResponse::fromJsonString($this->serializer->serialize($options));
    }
}
