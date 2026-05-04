<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Validator;

use Sylius\Bundle\UserBundle\Form\Model\ChangePassword;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\PolicyFactoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Validator\Constraint\PasswordPolicy;

class PasswordPolicyValidator extends ConstraintValidator implements PasswordPolicyValidatorInterface
{
    public function __construct(
        protected PolicyFactoryInterface $policyFactory,
        protected TokenStorageInterface $tokenStorage,
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

        $policyGroup = $constraint->policyGroup;

        if ($this->context->getObject() instanceof ChangePassword) {
            $user = $this->tokenStorage->getToken()?->getUser();
            $policyGroup = $user instanceof AdminUserInterface ? 'admin' : 'customer';
        }

        $scope = $policyGroup === 'admin' ? SettingsScope::ADMIN : SettingsScope::CUSTOMER;
        $policy = $this->policyFactory->passwordPolicy($scope);
        $password = (string) $value;
        $length = mb_strlen($password);

        if ($length < $policy->getMinLength()) {
            $this->context->buildViolation($constraint->minLengthMessage)
                ->setParameter('{{ limit }}', (string) $policy->getMinLength())
                ->addViolation()
            ;
        }

        if ($policy->getMaxLength() !== null && $length > $policy->getMaxLength()) {
            $this->context->buildViolation($constraint->maxLengthMessage)
                ->setParameter('{{ limit }}', (string) $policy->getMaxLength())
                ->addViolation()
            ;
        }

        if ($policy->isRequireUppercase() && preg_match('/[A-Z]/', $password) === 0) {
            $this->context->buildViolation($constraint->requireUppercaseMessage)
                ->addViolation()
            ;
        }

        if ($policy->isRequireLowercase() && preg_match('/[a-z]/', $password) === 0) {
            $this->context->buildViolation($constraint->requireLowercaseMessage)
                ->addViolation()
            ;
        }

        if ($policy->isRequireNumbers() && preg_match('/[0-9]/', $password) === 0) {
            $this->context->buildViolation($constraint->requireNumbersMessage)
                ->addViolation()
            ;
        }

        if ($policy->isRequireSpecialCharacters() && preg_match('/[\W_]/', $password) === 0) {
            $this->context->buildViolation($constraint->requireSpecialCharactersMessage)
                ->addViolation()
            ;
        }
    }
}
