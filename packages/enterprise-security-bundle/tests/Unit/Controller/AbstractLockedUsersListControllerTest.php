<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\EnterpriseSecurityBundle\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractLockedUsersListController;
use Twig\Environment;

#[CoversClass(AbstractLockedUsersListController::class)]
class AbstractLockedUsersListControllerTest extends TestCase
{
    public function testThrowsNotFoundWhenDisabled(): void
    {
        $controller = $this->makeController(twig: $this->createStub(Environment::class), enabled: false);

        $this->expectException(NotFoundHttpException::class);
        $controller();
    }

    public function testRendersTemplateWithLockedUsers(): void
    {
        $users = [new \stdClass(), new \stdClass()];

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('@Foo/locked.html.twig', [
                'users' => $users,
            ])
            ->willReturn('<html/>');

        $controller = $this->makeController(twig: $twig, enabled: true, users: $users);

        $response = $controller();

        self::assertSame('<html/>', $response->getContent());
    }

    /**
     * @param list<object> $users
     */
    protected function makeController(Environment $twig, bool $enabled, array $users = []): AbstractLockedUsersListController
    {
        return new class($twig, $enabled, $users) extends AbstractLockedUsersListController {
            /**
             * @param list<object> $users
             */
            public function __construct(
                Environment $twig,
                bool $enabled,
                protected array $users
            ) {
                parent::__construct($twig, $enabled);
            }

            protected function findAllLockedUsers(): iterable
            {
                return $this->users;
            }

            protected function getTemplate(): string
            {
                return '@Foo/locked.html.twig';
            }
        };
    }
}
