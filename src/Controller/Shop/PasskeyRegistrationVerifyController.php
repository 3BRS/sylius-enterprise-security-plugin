<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\CustomerPasskeyRegistrationVerifierInterface;

class PasskeyRegistrationVerifyController implements PasskeyRegistrationVerifyControllerInterface
{
    protected const MAX_LABEL_LENGTH = 64;

    public function __construct(
        protected CustomerPasskeyRegistrationVerifierInterface $verifier,
        protected EntityManagerInterface $entityManager,
        protected TokenStorageInterface $tokenStorage,
        protected LoggerInterface $logger,
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
        if (!$user instanceof ShopUserInterface) {
            throw new AccessDeniedHttpException();
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'Missing credential payload.'], Response::HTTP_BAD_REQUEST);
        }
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Missing credential payload.'], Response::HTTP_BAD_REQUEST);
        }

        $rawLabel = $payload['label'] ?? '';
        $label = is_string($rawLabel) ? trim($rawLabel) : '';
        if ($label === '') {
            $label = 'Passkey';
        }
        if (mb_strlen($label) > static::MAX_LABEL_LENGTH) {
            $label = mb_substr($label, 0, static::MAX_LABEL_LENGTH);
        }

        $credentialJson = $payload['credential'] ?? null;
        if (!is_string($credentialJson) || $credentialJson === '') {
            return new JsonResponse(['error' => 'Missing credential payload.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $credential = $this->verifier->verifyAndCreate($user, $credentialJson, $label, $request->getHost());
        } catch (\Throwable $exception) {
            $this->logger->info('three_brs.passkey.shop.registration_failed', [
                'reason' => $exception->getMessage(),
                'user_id' => $user->getId(),
            ]);

            return new JsonResponse(['error' => 'Passkey registration failed.'], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($credential);
        $this->entityManager->flush();

        return new JsonResponse(['ok' => true]);
    }
}
