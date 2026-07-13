<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Form\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUser;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\User\Model\User;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Extension\CustomerTypeExtension;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;

#[CoversClass(CustomerTypeExtension::class)]
class CustomerTypeExtensionTest extends TestCase
{
    /** @var list<array{event: string, listener: callable, priority: int}> */
    protected array $listeners = [];

    public function testKeepsTheAccountSyliusWouldDiscardWhenTheAdminEnablesAGuestCustomer(): void
    {
        $this->buildForm(customerPasswordLoginEnabled: false);

        $customer = new Customer();
        $customer->setUser($this->newAccount(enabled: true));

        $user = $customer->getUser();

        self::assertSame($user, $this->submit($customer)->getUser());
    }

    public function testLeavesTheCustomerAGuestWhenTheAccountIsNotEnabled(): void
    {
        // Editing a guest customer without ticking "Enabled" must not create an account for them.
        $this->buildForm(customerPasswordLoginEnabled: false);

        $customer = new Customer();
        $customer->setUser($this->newAccount(enabled: false));

        self::assertNull($this->submit($customer)->getUser());
    }

    public function testDoesNotTouchAnAccountThatAlreadyExists(): void
    {
        $this->buildForm(customerPasswordLoginEnabled: false);

        $customer = new Customer();
        $customer->setUser($this->existingAccount());

        $user = $customer->getUser();

        self::assertSame($user, $this->submit($customer)->getUser());
    }

    public function testRegistersNothingWhenCustomerPasswordLoginEnabled(): void
    {
        $this->buildForm(customerPasswordLoginEnabled: true);

        self::assertSame([], $this->listeners);
    }

    protected function buildForm(bool $customerPasswordLoginEnabled): void
    {
        $this->listeners = [];

        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('addEventListener')->willReturnCallback(
            function (string $event, callable $listener, int $priority = 0) use ($builder): FormBuilderInterface {
                $this->listeners[] = ['event' => $event, 'listener' => $listener, 'priority' => $priority];

                return $builder;
            },
        );

        $checker = $this->createStub(PasswordLoginCheckerInterface::class);
        $checker->method('isEnabled')->willReturnCallback(
            static fn (SettingsScope $scope): bool => $scope === SettingsScope::CUSTOMER
                ? $customerPasswordLoginEnabled
                : true,
        );

        (new CustomerTypeExtension($checker))->buildForm($builder, []);
    }

    /** Replays the submit: the extension's listeners with Sylius' AddUserFormSubscriber in between. */
    protected function submit(CustomerInterface $customer): CustomerInterface
    {
        $form = $this->createStub(FormInterface::class);

        $steps = [];
        foreach ($this->listeners as $listener) {
            if ($listener['event'] !== FormEvents::SUBMIT) {
                continue;
            }

            $steps[] = [
                'priority' => $listener['priority'],
                'run' => static fn () => ($listener['listener'])(new FormEvent($form, $customer)),
            ];
        }

        // Sylius' AddUserFormSubscriber subscribes to SUBMIT with the default priority.
        $steps[] = ['priority' => 0, 'run' => fn () => $this->replaySyliusSubscriber($customer)];

        usort($steps, static fn (array $a, array $b): int => $b['priority'] <=> $a['priority']);

        foreach ($steps as $step) {
            ($step['run'])();
        }

        return $customer;
    }

    /** The decision Sylius\Bundle\AdminBundle\Form\EventSubscriber\AddUserFormSubscriber::submit() makes. */
    protected function replaySyliusSubscriber(CustomerInterface $customer): void
    {
        $user = $customer->getUser();

        if ($user !== null && $user->getPlainPassword() === null && $user->getId() === null) {
            $customer->setUser(null);
        }
    }

    /** The shop user the form builds from the "Enabled" / "Verified" checkboxes on a guest customer. */
    protected function newAccount(bool $enabled): ShopUserInterface
    {
        $user = new ShopUser();
        $user->setEnabled($enabled);

        return $user;
    }

    protected function existingAccount(): ShopUserInterface
    {
        $user = new ShopUser();
        $user->setEnabled(true);

        $id = new \ReflectionProperty(User::class, 'id');
        $id->setValue($user, 42);

        return $user;
    }
}
