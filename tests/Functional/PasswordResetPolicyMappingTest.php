<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Sylius\Bundle\AdminBundle\Form\Model\PasswordReset as AdminPasswordReset;
use Sylius\Bundle\UserBundle\Form\Model\PasswordReset as ShopPasswordReset;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Kernel;
use ThreeBRS\EnterpriseSecurityBundle\PasswordPolicy\Constraint\PasswordPolicy;

/**
 * The forgotten-password flow validates a PasswordReset model rather than the
 * ShopUser / AdminUser entity, so the policy mapped on those entities never sees
 * the new password. Sylius maps only NotBlank and Length(min=4) on these two
 * models, which left the password policy — a feature with no `enabled` switch,
 * active from installation — fully bypassable through a link sent by e-mail.
 *
 * The mapping that closes it is a single XML file with no PHP behind it, and
 * nothing else in the suite would notice its removal, hence this test.
 */
class PasswordResetPolicyMappingTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Booting the Sylius kernel registers an exception handler that is not
        // popped on shutdown; restore it so PHPUnit does not flag the test as
        // risky for leaking global handler state.
        restore_exception_handler();
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function resetModelProvider(): iterable
    {
        yield 'shop' => [ShopPasswordReset::class, 'customer'];
        yield 'admin' => [AdminPasswordReset::class, 'admin'];
    }

    /**
     * @param class-string $modelClass
     */
    #[DataProvider('resetModelProvider')]
    public function testTheResetModelCarriesThePolicyForItsGroup(string $modelClass, string $expectedPolicyGroup): void
    {
        self::bootKernel(['environment' => 'test', 'debug' => true]);

        /** @var ValidatorInterface $validator */
        $validator = self::getContainer()->get('validator');

        $metadata = $validator->getMetadataFor($modelClass);
        self::assertInstanceOf(ClassMetadataInterface::class, $metadata);

        $policies = [];
        foreach ($metadata->getPropertyMetadata('password') as $propertyMetadata) {
            foreach ($propertyMetadata->getConstraints() as $constraint) {
                if ($constraint instanceof PasswordPolicy) {
                    $policies[] = $constraint;
                }
            }
        }

        self::assertCount(1, $policies, sprintf('%s::$password should carry exactly one PasswordPolicy constraint.', $modelClass));
        self::assertSame($expectedPolicyGroup, $policies[0]->policyGroup);

        // The reset form validates in the "sylius" group, so a constraint outside
        // it would be mapped and still never run.
        self::assertContains('sylius', (array) $policies[0]->groups);
    }
}
