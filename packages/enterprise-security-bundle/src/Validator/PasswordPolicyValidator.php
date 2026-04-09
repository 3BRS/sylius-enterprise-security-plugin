<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityBundle\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use ThreeBRS\SyliusEnterpriseSecurityBundle\Model\PasswordPolicyInterface;
use ThreeBRS\SyliusEnterpriseSecurityBundle\Validator\Constraint\PasswordPolicy;

class PasswordPolicyValidator extends ConstraintValidator implements PasswordPolicyValidatorInterface
{
    /** @param array<string, PasswordPolicyInterface> $policies */
    public function __construct(
        private array $policies,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PasswordPolicy) {
            throw new UnexpectedTypeException($constraint, PasswordPolicy::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $policy = $this->policies[$constraint->policyGroup] ?? $this->policies['default'] ?? null;

        if ($policy === null) {
            return;
        }

        $password = (string) $value;
        $length = mb_strlen($password);

        if ($length < $policy->getMinLength()) {
            $this->context->buildViolation($constraint->minLengthMessage)
                ->setParameter('{{ limit }}', (string) $policy->getMinLength())
                ->setInvalidValue($value)
                ->addViolation()
            ;
        }

        if ($policy->getMaxLength() !== null && $length > $policy->getMaxLength()) {
            $this->context->buildViolation($constraint->maxLengthMessage)
                ->setParameter('{{ limit }}', (string) $policy->getMaxLength())
                ->setInvalidValue($value)
                ->addViolation()
            ;
        }

        if ($policy->isRequireUppercase() && preg_match('/[A-Z]/', $password) === 0) {
            $this->context->buildViolation($constraint->requireUppercaseMessage)
                ->setInvalidValue($value)
                ->addViolation()
            ;
        }

        if ($policy->isRequireLowercase() && preg_match('/[a-z]/', $password) === 0) {
            $this->context->buildViolation($constraint->requireLowercaseMessage)
                ->setInvalidValue($value)
                ->addViolation()
            ;
        }

        if ($policy->isRequireNumbers() && preg_match('/[0-9]/', $password) === 0) {
            $this->context->buildViolation($constraint->requireNumbersMessage)
                ->setInvalidValue($value)
                ->addViolation()
            ;
        }

        if ($policy->isRequireSpecialCharacters() && preg_match('/[\W_]/', $password) === 0) {
            $this->context->buildViolation($constraint->requireSpecialCharactersMessage)
                ->setInvalidValue($value)
                ->addViolation()
            ;
        }
    }
}
