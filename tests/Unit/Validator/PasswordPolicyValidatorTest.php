<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Validator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordPolicy;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Validator\Constraint\PasswordPolicy as PasswordPolicyConstraint;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Validator\PasswordPolicyValidator;

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
        int $customerMinLength = 8,
        ?int $customerMaxLength = null,
        bool $customerRequireUppercase = false,
        bool $customerRequireLowercase = false,
        bool $customerRequireNumbers = false,
        bool $customerRequireSpecialChars = false,
        int $adminMinLength = 12,
        ?int $adminMaxLength = null,
        bool $adminRequireUppercase = true,
        bool $adminRequireLowercase = true,
        bool $adminRequireNumbers = true,
        bool $adminRequireSpecialChars = true,
    ): PasswordPolicyValidator {
        $customerPolicy = new PasswordPolicy(
            $customerMinLength,
            $customerMaxLength,
            $customerRequireUppercase,
            $customerRequireLowercase,
            $customerRequireNumbers,
            $customerRequireSpecialChars,
        );

        $adminPolicy = new PasswordPolicy(
            $adminMinLength,
            $adminMaxLength,
            $adminRequireUppercase,
            $adminRequireLowercase,
            $adminRequireNumbers,
            $adminRequireSpecialChars,
        );

        $validator = new PasswordPolicyValidator($customerPolicy, $adminPolicy);
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

    public function testValidPasswordPassesCustomerPolicy(): void
    {
        $this->context->expects(self::never())->method('buildViolation');

        $validator = $this->createValidator(customerMinLength: 8);
        $constraint = new PasswordPolicyConstraint();
        $constraint->policyGroup = 'customer';

        $validator->validate('validpass', $constraint);
    }

    public function testPasswordTooShortForCustomerPolicy(): void
    {
        $this->context
            ->expects(self::once())
            ->method('buildViolation')
            ->with('three_brs.password_policy.min_length')
            ->willReturn($this->violationBuilder)
        ;

        $validator = $this->createValidator(customerMinLength: 8);
        $constraint = new PasswordPolicyConstraint();
        $constraint->policyGroup = 'customer';

        $validator->validate('short', $constraint);
    }

    public function testPasswordTooLongForCustomerPolicy(): void
    {
        $this->context
            ->expects(self::once())
            ->method('buildViolation')
            ->with('three_brs.password_policy.max_length')
            ->willReturn($this->violationBuilder)
        ;

        $validator = $this->createValidator(customerMinLength: 1, customerMaxLength: 10);
        $constraint = new PasswordPolicyConstraint();
        $constraint->policyGroup = 'customer';

        $validator->validate('this_password_is_way_too_long', $constraint);
    }

    public function testRequireUppercaseViolation(): void
    {
        $this->context
            ->expects(self::once())
            ->method('buildViolation')
            ->with('three_brs.password_policy.require_uppercase')
            ->willReturn($this->violationBuilder)
        ;

        $validator = $this->createValidator(customerMinLength: 1, customerRequireUppercase: true);
        $constraint = new PasswordPolicyConstraint();
        $constraint->policyGroup = 'customer';

        $validator->validate('nouppercase1!', $constraint);
    }

    public function testRequireLowercaseViolation(): void
    {
        $this->context
            ->expects(self::once())
            ->method('buildViolation')
            ->with('three_brs.password_policy.require_lowercase')
            ->willReturn($this->violationBuilder)
        ;

        $validator = $this->createValidator(customerMinLength: 1, customerRequireLowercase: true);
        $constraint = new PasswordPolicyConstraint();
        $constraint->policyGroup = 'customer';

        $validator->validate('NOLOWERCASE1!', $constraint);
    }

    public function testRequireNumbersViolation(): void
    {
        $this->context
            ->expects(self::once())
            ->method('buildViolation')
            ->with('three_brs.password_policy.require_numbers')
            ->willReturn($this->violationBuilder)
        ;

        $validator = $this->createValidator(customerMinLength: 1, customerRequireNumbers: true);
        $constraint = new PasswordPolicyConstraint();
        $constraint->policyGroup = 'customer';

        $validator->validate('NoNumbers!', $constraint);
    }

    public function testRequireSpecialCharactersViolation(): void
    {
        $this->context
            ->expects(self::once())
            ->method('buildViolation')
            ->with('three_brs.password_policy.require_special_characters')
            ->willReturn($this->violationBuilder)
        ;

        $validator = $this->createValidator(customerMinLength: 1, customerRequireSpecialChars: true);
        $constraint = new PasswordPolicyConstraint();
        $constraint->policyGroup = 'customer';

        $validator->validate('NoSpecialChars1', $constraint);
    }

    public function testAllComplexityRequirementsSatisfied(): void
    {
        $this->context->expects(self::never())->method('buildViolation');

        $validator = $this->createValidator(
            customerMinLength: 8,
            customerRequireUppercase: true,
            customerRequireLowercase: true,
            customerRequireNumbers: true,
            customerRequireSpecialChars: true,
        );
        $constraint = new PasswordPolicyConstraint();
        $constraint->policyGroup = 'customer';

        $validator->validate('ValidPass1!', $constraint);
    }

    public function testPasswordExactlyAtMinLengthPassesValidation(): void
    {
        $this->context->expects(self::never())->method('buildViolation');

        $validator = $this->createValidator(customerMinLength: 5);
        $constraint = new PasswordPolicyConstraint();
        $constraint->policyGroup = 'customer';

        $validator->validate('passw', $constraint); // exactly 5 chars
    }

    public function testPasswordExactlyAtMaxLengthPassesValidation(): void
    {
        $this->context->expects(self::never())->method('buildViolation');

        $validator = $this->createValidator(customerMinLength: 1, customerMaxLength: 5);
        $constraint = new PasswordPolicyConstraint();
        $constraint->policyGroup = 'customer';

        $validator->validate('passw', $constraint); // exactly 5 chars
    }

    public function testMultibytePasswordCountsCharactersNotBytes(): void
    {
        $this->context->expects(self::never())->method('buildViolation');

        $validator = $this->createValidator(customerMinLength: 5);
        $constraint = new PasswordPolicyConstraint();
        $constraint->policyGroup = 'customer';

        $validator->validate('héšlö', $constraint); // 5 chars, 7 bytes
    }

    public function testUnknownPolicyGroupFallsBackToCustomerPolicy(): void
    {
        $this->context
            ->expects(self::once())
            ->method('buildViolation')
            ->with('three_brs.password_policy.min_length')
            ->willReturn($this->violationBuilder)
        ;

        // customerMinLength=10, adminMinLength=4: unknown group must use customer (not admin)
        // 'ValidPs!' = 8 chars → fails customer (10), would pass admin (4)
        $validator = $this->createValidator(customerMinLength: 10, adminMinLength: 4);
        $constraint = new PasswordPolicyConstraint();
        $constraint->policyGroup = 'shop';

        $validator->validate('ValidPs!', $constraint);
    }

    public function testAdminPolicyIsUsedForAdminGroup(): void
    {
        $this->context
            ->expects(self::once())
            ->method('buildViolation')
            ->with('three_brs.password_policy.min_length')
            ->willReturn($this->violationBuilder)
        ;

        $validator = $this->createValidator(customerMinLength: 8, adminMinLength: 16);
        $constraint = new PasswordPolicyConstraint();
        $constraint->policyGroup = 'admin';

        // 11 chars: passes customer (8) but fails admin (16); satisfies all complexity reqs
        $validator->validate('ValidPass1!', $constraint);
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

        $validator = $this->createValidator(customerMinLength: 1, customerRequireSpecialChars: true);
        $constraint = new PasswordPolicyConstraint();
        $constraint->policyGroup = 'customer';

        $validator->validate($password, $constraint);
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
            customerMinLength: 1,
            customerRequireUppercase: true,
            customerRequireNumbers: true,
        );
        $constraint = new PasswordPolicyConstraint();
        $constraint->policyGroup = 'customer';

        // missing uppercase AND numbers
        $validator->validate('noupperornum!', $constraint);
    }
}
