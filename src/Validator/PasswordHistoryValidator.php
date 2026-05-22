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
use ThreeBRS\EnterpriseSecurityBundle\PasswordHistory\Constraint\PasswordHistory;
use ThreeBRS\EnterpriseSecurityBundle\PasswordHistory\PasswordHistoryValidatorInterface;
use ThreeBRS\EnterpriseSecurityBundle\PasswordHistory\PasswordSimilarityCheckerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordHistoryCheckerInterface;

class PasswordHistoryValidator extends ConstraintValidator implements PasswordHistoryValidatorInterface
{
    protected const SIMILAR_TO_CURRENT_MESSAGE = 'three_brs.password_history.similar_to_current';

    public function __construct(
        protected PasswordHistoryCheckerInterface $checker,
        protected PasswordSimilarityCheckerInterface $similarityChecker,
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

        // Similarity check against the form-supplied current password — covers
        // the "1234" → "12345" case the bcrypt history lookup cannot detect
        // because each hash is unique to its plain. Only available in flows
        // where the user submits their current password (shop ChangePassword
        // form); admin-edits-another-admin form has no currentPassword field.
        if ($object instanceof ChangePassword) {
            $currentPassword = (string) $object->getCurrentPassword();
            if ($currentPassword !== '' && $this->similarityChecker->isSimilar($currentPassword, $plainPassword)) {
                $this->context->buildViolation(self::SIMILAR_TO_CURRENT_MESSAGE)->addViolation();
            }
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
