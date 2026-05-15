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
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordHistoryCheckerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Validator\Constraint\PasswordHistory;

class PasswordHistoryValidator extends ConstraintValidator implements PasswordHistoryValidatorInterface
{
    public function __construct(
        protected PasswordHistoryCheckerInterface $checker,
        protected TokenStorageInterface $tokenStorage,
        protected SettingsProviderInterface $settings,
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

        if ($object instanceof AdminUserInterface) {
            $this->validateForAdminUser($object, $plainPassword, $constraint);

            return;
        }

        if ($object === null || $object instanceof ChangePassword) {
            $this->validateFromTokenStorage($plainPassword, $constraint);

            return;
        }

        throw new ConstraintDefinitionException(sprintf(
            'The %s constraint can only be applied to AdminUserInterface or ChangePassword objects; got %s.',
            PasswordHistory::class,
            $object::class,
        ));
    }

    protected function validateForShopUser(ShopUserInterface $user, string $plainPassword, PasswordHistory $constraint): void
    {
        $enabled = $this->settings->getBool('password_history.enabled', SettingsScope::CUSTOMER);
        $count = $this->settings->getInt('password_history.count', SettingsScope::CUSTOMER);

        if (!$enabled || $user->getId() === null) {
            return;
        }

        if ($this->checker->wasPasswordUsedByShopUser($user, $plainPassword, $count)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ count }}', (string) $count)
                ->addViolation()
            ;
        }
    }

    protected function validateForAdminUser(AdminUserInterface $user, string $plainPassword, PasswordHistory $constraint): void
    {
        $enabled = $this->settings->getBool('password_history.enabled', SettingsScope::ADMIN);
        $count = $this->settings->getInt('password_history.count', SettingsScope::ADMIN);

        if (!$enabled || $user->getId() === null) {
            return;
        }

        if ($this->checker->wasPasswordUsedByAdminUser($user, $plainPassword, $count)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ count }}', (string) $count)
                ->addViolation()
            ;
        }
    }

    protected function validateFromTokenStorage(string $plainPassword, PasswordHistory $constraint): void
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
