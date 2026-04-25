<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\AdminPasskeyRegistrationVerifierInterface;

class PasskeyRegistrationVerifyController implements PasskeyRegistrationVerifyControllerInterface
{
    protected const MAX_LABEL_LENGTH = 64;

    public function __construct(
        protected AdminPasskeyRegistrationVerifierInterface $verifier,
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
        if (!$user instanceof AdminUserInterface) {
            throw new AccessDeniedHttpException();
        }

        $payload = (array) json_decode($request->getContent(), true);
        $label = trim((string) ($payload['label'] ?? ''));
        if ($label === '') {
            $label = 'Passkey';
        }
        if (mb_strlen($label) > static::MAX_LABEL_LENGTH) {
            $label = mb_substr($label, 0, static::MAX_LABEL_LENGTH);
        }

        $credentialJson = (string) ($payload['credential'] ?? '');
        if ($credentialJson === '') {
            return new JsonResponse(['error' => 'Missing credential payload.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $credential = $this->verifier->verifyAndCreate($user, $credentialJson, $label, $request->getHost());
        } catch (\Throwable $exception) {
            $this->logger->info('three_brs.passkey.admin.registration_failed', [
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
