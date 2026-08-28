<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\Range;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Kernel;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\SecuritySettingsType;

/**
 * Every number an administrator can type into Security Settings has its bound
 * written down twice: once as the `min`/`max` the browser enforces, and once as a
 * Range constraint the server enforces. They are separate arguments to the same
 * ->add() call, so nothing makes them agree, and disagreeing is quiet in the worse
 * direction - a field whose Range was forgotten takes any value a plain POST
 * carries, however the input spinner is labelled.
 *
 * The fields come from the built form rather than from a list, so a setting added
 * tomorrow is covered tomorrow. Rate limits are the reason that matters: they are
 * generated per action, and the actions differ between the two scopes.
 */
class SettingsFormBoundsTest extends KernelTestCase
{
    protected FormFactoryInterface $formFactory;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel(['environment' => 'test', 'debug' => true]);

        /** @var FormFactoryInterface $formFactory */
        $formFactory = self::getContainer()->get('form.factory');
        $this->formFactory = $formFactory;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Booting the Sylius kernel leaves an exception handler on the stack.
        restore_exception_handler();
    }

    public function testEveryNumberTheFormOffersIsBoundedOnTheServerToo(): void
    {
        $checked = 0;

        foreach ([SettingsScope::CUSTOMER, SettingsScope::ADMIN] as $scope) {
            foreach ($this->numericFields($this->buildSettingsForm($scope)) as $path => $field) {
                $where = sprintf('%s scope, field "%s"', $scope->value, $path);
                $config = $field->getConfig();

                /** @var array<string, mixed> $attr */
                $attr = $config->getOption('attr');
                $range = $this->rangeOf($field);

                self::assertNotNull($range, sprintf('%s: a number with no Range - a POST past the spinner sets anything.', $where));
                self::assertSame($attr['min'] ?? null, $range->min, sprintf('%s: the browser and the server disagree about the lowest value.', $where));
                self::assertSame($attr['max'] ?? null, $range->max, sprintf('%s: the browser and the server disagree about the highest value.', $where));

                ++$checked;
            }
        }

        self::assertGreaterThan(0, $checked, 'No numeric setting was found - the form tree is not what this test assumes.');
    }

    protected function buildSettingsForm(SettingsScope $scope): FormInterface
    {
        return $this->formFactory->create(SecuritySettingsType::class, null, ['scope' => $scope]);
    }

    /**
     * @return iterable<string, FormInterface>
     */
    protected function numericFields(FormInterface $form, string $prefix = ''): iterable
    {
        foreach ($form as $name => $child) {
            $path = $prefix === '' ? (string) $name : $prefix . '.' . $name;

            if ($child->count() > 0) {
                yield from $this->numericFields($child, $path);

                continue;
            }

            if ($child->getConfig()->getType()->getInnerType() instanceof IntegerType) {
                yield $path => $child;
            }
        }
    }

    protected function rangeOf(FormInterface $field): ?Range
    {
        /** @var array<int, object> $constraints */
        $constraints = $field->getConfig()->getOption('constraints');

        foreach ($constraints as $constraint) {
            if ($constraint instanceof Range) {
                return $constraint;
            }
        }

        return null;
    }
}
