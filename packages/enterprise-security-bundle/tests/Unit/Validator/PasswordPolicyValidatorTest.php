<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\EnterpriseSecurityBundle\Unit\Validator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Model\PasswordPolicy;
use ThreeBRS\EnterpriseSecurityBundle\Validator\Constraint\PasswordPolicy as PasswordPolicyConstraint;
use ThreeBRS\EnterpriseSecurityBundle\Validator\PasswordPolicyValidator;

#[CoversClass(PasswordPolicyValidator::class)]
class PasswordPolicyValidatorTest extends TestCase
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
        int $minLength = 8,
        ?int $maxLength = null,
        bool $requireUppercase = false,
        bool $requireLowercase = false,
        bool $requireNumbers = false,
        bool $requireSpecialChars = false,
    ): PasswordPolicyValidator {
        $policy = new PasswordPolicy(
            $minLength,
            $maxLength,
            $requireUppercase,
            $requireLowercase,
            $requireNumbers,
            $requireSpecialChars,
        );

        $validator = new PasswordPolicyValidator(['default' => $policy]);
        $validator->initialize($this->context);

        return $validator;
    }

    public function testSkipsValidationForNullValue(): void
    {
        $this->context->expects(self::never())->method('buildViolation');

        $validator = $this->createValidator();
        $validator->validate(null, new PasswordPolicyConstraint());
    }

    public function testSkipsValidationForEmptyString(): void
    {
        $this->context->expects(self::never())->method('buildViolation');

        $validator = $this->createValidator();
        $validator->validate('', new PasswordPolicyConstraint());
    }

    public function testValidPasswordPassesPolicy(): void
    {
        $this->context->expects(self::never())->method('buildViolation');

        $validator = $this->createValidator(minLength: 8);
        $validator->validate('validpass', new PasswordPolicyConstraint());
    }

    public function testPasswordTooShort(): void
    {
        $this->context
            ->expects(self::once())
            ->method('buildViolation')
            ->with('three_brs.password_policy.min_length')
            ->willReturn($this->violationBuilder)
        ;

        $validator = $this->createValidator(minLength: 8);
        $validator->validate('short', new PasswordPolicyConstraint());
    }

    public function testPasswordTooLong(): void
    {
        $this->context
            ->expects(self::once())
            ->method('buildViolation')
            ->with('three_brs.password_policy.max_length')
            ->willReturn($this->violationBuilder)
        ;

        $validator = $this->createValidator(minLength: 1, maxLength: 10);
        $validator->validate('this_password_is_way_too_long', new PasswordPolicyConstraint());
    }

    public function testRequireUppercaseViolation(): void
    {
        $this->context
            ->expects(self::once())
            ->method('buildViolation')
            ->with('three_brs.password_policy.require_uppercase')
            ->willReturn($this->violationBuilder)
        ;

        $validator = $this->createValidator(minLength: 1, requireUppercase: true);
        $validator->validate('nouppercase1!', new PasswordPolicyConstraint());
    }

    public function testRequireLowercaseViolation(): void
    {
        $this->context
            ->expects(self::once())
            ->method('buildViolation')
            ->with('three_brs.password_policy.require_lowercase')
            ->willReturn($this->violationBuilder)
        ;

        $validator = $this->createValidator(minLength: 1, requireLowercase: true);
        $validator->validate('NOLOWERCASE1!', new PasswordPolicyConstraint());
    }

    public function testRequireNumbersViolation(): void
    {
        $this->context
            ->expects(self::once())
            ->method('buildViolation')
            ->with('three_brs.password_policy.require_numbers')
            ->willReturn($this->violationBuilder)
        ;

        $validator = $this->createValidator(minLength: 1, requireNumbers: true);
        $validator->validate('NoNumbers!', new PasswordPolicyConstraint());
    }

    public function testRequireSpecialCharactersViolation(): void
    {
        $this->context
            ->expects(self::once())
            ->method('buildViolation')
            ->with('three_brs.password_policy.require_special_characters')
            ->willReturn($this->violationBuilder)
        ;

        $validator = $this->createValidator(minLength: 1, requireSpecialChars: true);
        $validator->validate('NoSpecialChars1', new PasswordPolicyConstraint());
    }

    public function testAllComplexityRequirementsSatisfied(): void
    {
        $this->context->expects(self::never())->method('buildViolation');

        $validator = $this->createValidator(
            minLength: 8,
            requireUppercase: true,
            requireLowercase: true,
            requireNumbers: true,
            requireSpecialChars: true,
        );

        $validator->validate('ValidPass1!', new PasswordPolicyConstraint());
    }

    public function testUnknownPolicyGroupSkipsValidation(): void
    {
        $this->context->expects(self::never())->method('buildViolation');

        $validator = $this->createValidator();
        $constraint = new PasswordPolicyConstraint();
        $constraint->policyGroup = 'nonexistent';

        $validator->validate('anypassword', $constraint);
    }

    #[DataProvider('specialCharactersProvider')]
    public function testSpecialCharacterVariants(string $password, bool $expectViolation): void
    {
        if ($expectViolation) {
            $this->context
                ->expects(self::atLeastOnce())
                ->method('buildViolation')
                ->willReturn($this->violationBuilder)
            ;
        } else {
            $this->context->expects(self::never())->method('buildViolation');
        }

        $validator = $this->createValidator(minLength: 1, requireSpecialChars: true);
        $validator->validate($password, new PasswordPolicyConstraint());
    }

    public static function specialCharactersProvider(): array
    {
        return [
            'exclamation mark' => ['pass!', false],
            'at sign' => ['pass@', false],
            'hash' => ['pass#', false],
            'underscore' => ['pass_', false],
            'no special char' => ['password', true],
            'only letters and numbers' => ['Password1', true],
        ];
    }

    public function testMultipleViolationsReportedTogether(): void
    {
        $this->context
            ->expects(self::exactly(2))
            ->method('buildViolation')
            ->willReturn($this->violationBuilder)
        ;

        $validator = $this->createValidator(
            minLength: 1,
            requireUppercase: true,
            requireNumbers: true,
        );

        $validator->validate('noupperornum!', new PasswordPolicyConstraint());
    }
}
