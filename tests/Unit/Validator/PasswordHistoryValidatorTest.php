<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Validator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\UserBundle\Form\Model\ChangePassword;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordHistoryCheckerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Validator\Constraint\PasswordHistory;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Validator\PasswordHistoryValidator;

#[CoversClass(PasswordHistoryValidator::class)]
class PasswordHistoryValidatorTest extends TestCase
{
    private ExecutionContextInterface&MockObject $context;

    private ConstraintViolationBuilderInterface $violationBuilder;

    protected function setUp(): void
    {
        $this->context = $this->createMock(ExecutionContextInterface::class);
        $this->violationBuilder = $this->createStub(ConstraintViolationBuilderInterface::class);

        $this->violationBuilder->method('setParameter')->willReturnSelf();
        $this->violationBuilder->method('setInvalidValue')->willReturnSelf();
        $this->violationBuilder->method('addViolation');
    }

    private function createValidator(
        ?PasswordHistoryCheckerInterface $checker = null,
        ?TokenStorageInterface $tokenStorage = null,
        bool $customerEnabled = true,
        int $customerCount = 5,
        bool $adminEnabled = true,
        int $adminCount = 10,
    ): PasswordHistoryValidator {
        $validator = new PasswordHistoryValidator(
            $checker ?? $this->createStub(PasswordHistoryCheckerInterface::class),
            $tokenStorage ?? $this->createStub(TokenStorageInterface::class),
            $customerEnabled,
            $customerCount,
            $adminEnabled,
            $adminCount,
        );
        $validator->initialize($this->context);

        return $validator;
    }

    private function shopUser(?int $id = 1): ShopUserInterface
    {
        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getId')->willReturn($id);

        return $user;
    }

    private function adminUser(?int $id = 1): AdminUserInterface
    {
        $user = $this->createStub(AdminUserInterface::class);
        $user->method('getId')->willReturn($id);

        return $user;
    }

    public function testThrowsOnWrongConstraintType(): void
    {
        $this->context->expects(self::never())->method('buildViolation');
        $this->expectException(UnexpectedTypeException::class);

        $this->createValidator()->validate('anything', $this->createStub(Constraint::class));
    }

    public function testSkipsValidationForNullValue(): void
    {
        $this->context->expects(self::never())->method('buildViolation');

        $this->createValidator()->validate(null, new PasswordHistory());
    }

    public function testSkipsValidationForEmptyValue(): void
    {
        $this->context->expects(self::never())->method('buildViolation');

        $this->createValidator()->validate('', new PasswordHistory());
    }

    public function testBuildsViolationWhenShopUserPasswordWasReused(): void
    {
        $user = $this->shopUser();

        $checker = $this->createMock(PasswordHistoryCheckerInterface::class);
        $checker->method('wasPasswordUsedByShopUser')
            ->with($user, 'oldPass', 5)
            ->willReturn(true);

        $this->context->method('getObject')->willReturn($user);
        $this->context->expects(self::once())
            ->method('buildViolation')
            ->willReturn($this->violationBuilder);

        $this->createValidator(checker: $checker, customerCount: 5)
            ->validate('oldPass', new PasswordHistory());
    }

    public function testSkipsShopUserValidationWhenCustomerDisabled(): void
    {
        $checker = $this->createMock(PasswordHistoryCheckerInterface::class);
        $checker->expects(self::never())->method('wasPasswordUsedByShopUser');

        $this->context->method('getObject')->willReturn($this->shopUser());
        $this->context->expects(self::never())->method('buildViolation');

        $this->createValidator(checker: $checker, customerEnabled: false)
            ->validate('oldPass', new PasswordHistory());
    }

    public function testSkipsShopUserValidationWhenUserHasNoId(): void
    {
        $checker = $this->createMock(PasswordHistoryCheckerInterface::class);
        $checker->expects(self::never())->method('wasPasswordUsedByShopUser');

        $this->context->method('getObject')->willReturn($this->shopUser(id: null));
        $this->context->expects(self::never())->method('buildViolation');

        $this->createValidator(checker: $checker)->validate('oldPass', new PasswordHistory());
    }

    public function testDoesNothingWhenShopUserPasswordNotInHistory(): void
    {
        $checker = $this->createStub(PasswordHistoryCheckerInterface::class);
        $checker->method('wasPasswordUsedByShopUser')->willReturn(false);

        $this->context->method('getObject')->willReturn($this->shopUser());
        $this->context->expects(self::never())->method('buildViolation');

        $this->createValidator(checker: $checker)->validate('newPass', new PasswordHistory());
    }

    public function testBuildsViolationWhenAdminUserPasswordWasReused(): void
    {
        $user = $this->adminUser();

        $checker = $this->createMock(PasswordHistoryCheckerInterface::class);
        $checker->method('wasPasswordUsedByAdminUser')
            ->with($user, 'oldAdminPass', 10)
            ->willReturn(true);

        $this->context->method('getObject')->willReturn($user);
        $this->context->expects(self::once())
            ->method('buildViolation')
            ->willReturn($this->violationBuilder);

        $this->createValidator(checker: $checker, adminCount: 10)
            ->validate('oldAdminPass', new PasswordHistory());
    }

    public function testSkipsAdminUserValidationWhenAdminDisabled(): void
    {
        $checker = $this->createMock(PasswordHistoryCheckerInterface::class);
        $checker->expects(self::never())->method('wasPasswordUsedByAdminUser');

        $this->context->method('getObject')->willReturn($this->adminUser());
        $this->context->expects(self::never())->method('buildViolation');

        $this->createValidator(checker: $checker, adminEnabled: false)
            ->validate('oldPass', new PasswordHistory());
    }

    public function testSkipsAdminUserValidationWhenUserHasNoId(): void
    {
        $checker = $this->createMock(PasswordHistoryCheckerInterface::class);
        $checker->expects(self::never())->method('wasPasswordUsedByAdminUser');

        $this->context->method('getObject')->willReturn($this->adminUser(id: null));
        $this->context->expects(self::never())->method('buildViolation');

        $this->createValidator(checker: $checker)->validate('oldPass', new PasswordHistory());
    }

    public function testFallsBackToTokenStorageForChangePasswordFormAndValidatesShopUser(): void
    {
        $user = $this->shopUser();

        $checker = $this->createStub(PasswordHistoryCheckerInterface::class);
        $checker->method('wasPasswordUsedByShopUser')->willReturn(true);

        $tokenStorage = $this->stubTokenStorageWithUser($user);

        $this->context->method('getObject')->willReturn(new ChangePassword());
        $this->context->expects(self::once())
            ->method('buildViolation')
            ->willReturn($this->violationBuilder);

        $this->createValidator(checker: $checker, tokenStorage: $tokenStorage)
            ->validate('oldPass', new PasswordHistory());
    }

    public function testFallsBackToTokenStorageForChangePasswordFormAndValidatesAdminUser(): void
    {
        $user = $this->adminUser();

        $checker = $this->createStub(PasswordHistoryCheckerInterface::class);
        $checker->method('wasPasswordUsedByAdminUser')->willReturn(true);

        $tokenStorage = $this->stubTokenStorageWithUser($user);

        $this->context->method('getObject')->willReturn(new ChangePassword());
        $this->context->expects(self::once())
            ->method('buildViolation')
            ->willReturn($this->violationBuilder);

        $this->createValidator(checker: $checker, tokenStorage: $tokenStorage)
            ->validate('oldAdminPass', new PasswordHistory());
    }

    public function testDoesNothingForChangePasswordWhenNoTokenPresent(): void
    {
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $checker = $this->createMock(PasswordHistoryCheckerInterface::class);
        $checker->expects(self::never())->method('wasPasswordUsedByShopUser');
        $checker->expects(self::never())->method('wasPasswordUsedByAdminUser');

        $this->context->method('getObject')->willReturn(new ChangePassword());
        $this->context->expects(self::never())->method('buildViolation');

        $this->createValidator(checker: $checker, tokenStorage: $tokenStorage)
            ->validate('anyPass', new PasswordHistory());
    }

    public function testThrowsForUnknownObjectType(): void
    {
        $checker = $this->createMock(PasswordHistoryCheckerInterface::class);
        $checker->expects(self::never())->method('wasPasswordUsedByShopUser');
        $checker->expects(self::never())->method('wasPasswordUsedByAdminUser');

        $this->context->method('getObject')->willReturn(new \stdClass());
        $this->context->expects(self::never())->method('buildViolation');

        $this->expectException(ConstraintDefinitionException::class);

        $this->createValidator(checker: $checker)->validate('anyPass', new PasswordHistory());
    }

    public function testFallsBackToTokenStorageWhenObjectIsNull(): void
    {
        $user = $this->shopUser();

        $checker = $this->createStub(PasswordHistoryCheckerInterface::class);
        $checker->method('wasPasswordUsedByShopUser')->willReturn(true);

        $tokenStorage = $this->stubTokenStorageWithUser($user);

        $this->context->method('getObject')->willReturn(null);
        $this->context->expects(self::once())
            ->method('buildViolation')
            ->willReturn($this->violationBuilder);

        $this->createValidator(checker: $checker, tokenStorage: $tokenStorage)
            ->validate('oldPass', new PasswordHistory());
    }

    private function stubTokenStorageWithUser(object $user): TokenStorageInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        return $tokenStorage;
    }
}
