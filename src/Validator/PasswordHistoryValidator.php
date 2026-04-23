<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Validator;

use Sylius\Bundle\UserBundle\Form\Model\ChangePassword;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordHistoryCheckerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Validator\Constraint\PasswordHistory;

class PasswordHistoryValidator extends ConstraintValidator implements PasswordHistoryValidatorInterface
{
    public function __construct(
        private PasswordHistoryCheckerInterface $checker,
        private TokenStorageInterface $tokenStorage,
        private bool $customerEnabled,
        private int $customerCount,
        private bool $adminEnabled,
        private int $adminCount,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PasswordHistory) {
            throw new UnexpectedTypeException($constraint, PasswordHistory::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $object = $this->context->getObject();
        $plainPassword = (string) $value;

        if ($object instanceof ShopUserInterface) {
            $this->validateForShopUser($object, $plainPassword, $constraint);

            return;
        }

        if ($object instanceof AdminUserInterface) {
            $this->validateForAdminUser($object, $plainPassword, $constraint);

            return;
        }

        if ($object === null || $object instanceof ChangePassword) {
            $this->validateFromTokenStorage($plainPassword, $constraint);

            return;
        }

        throw new ConstraintDefinitionException(sprintf(
            'The %s constraint can only be applied to ShopUserInterface, AdminUserInterface or ChangePassword objects; got %s.',
            PasswordHistory::class,
            $object::class,
        ));
    }

    private function validateForShopUser(ShopUserInterface $user, string $plainPassword, PasswordHistory $constraint): void
    {
        if (!$this->customerEnabled || $user->getId() === null) {
            return;
        }

        if ($this->checker->wasPasswordUsedByShopUser($user, $plainPassword, $this->customerCount)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ count }}', (string) $this->customerCount)
                ->addViolation()
            ;
        }
    }

    private function validateForAdminUser(AdminUserInterface $user, string $plainPassword, PasswordHistory $constraint): void
    {
        if (!$this->adminEnabled || $user->getId() === null) {
            return;
        }

        if ($this->checker->wasPasswordUsedByAdminUser($user, $plainPassword, $this->adminCount)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ count }}', (string) $this->adminCount)
                ->addViolation()
            ;
        }
    }

    private function validateFromTokenStorage(string $plainPassword, PasswordHistory $constraint): void
    {
        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return;
        }

        $user = $token->getUser();

        if ($user instanceof ShopUserInterface) {
            $this->validateForShopUser($user, $plainPassword, $constraint);

            return;
        }

        if ($user instanceof AdminUserInterface) {
            $this->validateForAdminUser($user, $plainPassword, $constraint);
        }
    }
}
