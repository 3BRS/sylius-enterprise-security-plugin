<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Controller\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\AccountDeletionCancelController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequestInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerDeletionRequestRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion\CustomerDeletionServiceInterface;

#[CoversClass(AccountDeletionCancelController::class)]
class AccountDeletionCancelControllerTest extends TestCase
{
    public function testCancelsAPendingRequest(): void
    {
        $deletionRequest = $this->createStub(CustomerDeletionRequestInterface::class);
        $admin = $this->createStub(AdminUserInterface::class);

        $repository = $this->createStub(CustomerDeletionRequestRepositoryInterface::class);
        $repository->method('findPendingById')->willReturn($deletionRequest);

        $service = $this->createMock(CustomerDeletionServiceInterface::class);
        $service->expects(self::once())->method('cancelByAdmin')->with($deletionRequest, $admin);

        self::assertTrue($this->cancel($repository, $service, $admin));
    }

    /**
     * cancelByAdmin() refuses a request that is no longer pending by throwing, and this
     * controller has no catch, so an administrator with two tabs open — or one whose
     * click raced the cron stamping completedAt — was answered with a 500. Reporting
     * "not found" lets the bundle controller render its 404 instead.
     */
    public function testReportsNotFoundForARequestThatIsNoLongerPending(): void
    {
        $repository = $this->createStub(CustomerDeletionRequestRepositoryInterface::class);
        $repository->method('findPendingById')->willReturn(null);

        $service = $this->createMock(CustomerDeletionServiceInterface::class);
        $service->expects(self::never())->method('cancelByAdmin');

        self::assertFalse($this->cancel($repository, $service, $this->createStub(AdminUserInterface::class)));
    }

    public function testReportsNotFoundWhenNoAdministratorIsSignedIn(): void
    {
        $repository = $this->createStub(CustomerDeletionRequestRepositoryInterface::class);
        $repository->method('findPendingById')->willReturn($this->createStub(CustomerDeletionRequestInterface::class));

        $service = $this->createMock(CustomerDeletionServiceInterface::class);
        $service->expects(self::never())->method('cancelByAdmin');

        self::assertFalse($this->cancel($repository, $service, null));
    }

    protected function cancel(
        CustomerDeletionRequestRepositoryInterface $repository,
        CustomerDeletionServiceInterface $service,
        ?AdminUserInterface $admin,
    ): bool {
        $token = null;
        if ($admin !== null) {
            $token = $this->createStub(TokenInterface::class);
            $token->method('getUser')->willReturn($admin);
        }

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $controller = new AccountDeletionCancelController(
            $repository,
            $service,
            $tokenStorage,
            $this->createStub(CsrfTokenManagerInterface::class),
            $this->createStub(RouterInterface::class),
            true,
        );

        // The decision lives in a protected hook the bundle controller calls; reaching it
        // through __invoke() would only add CSRF plumbing that is tested elsewhere.
        $method = new \ReflectionMethod($controller, 'cancelDeletionRequest');

        return (bool) $method->invoke($controller, 42);
    }
}
