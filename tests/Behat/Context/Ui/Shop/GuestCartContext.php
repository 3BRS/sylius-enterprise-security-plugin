<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use FriendsOfBehat\SymfonyExtension\Context\Environment\InitializedSymfonyExtensionEnvironment;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Core\Repository\ProductVariantRepositoryInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Webmozart\Assert\Assert;

class GuestCartContext implements Context
{
    protected const CART_SESSION_KEY_PREFIX = '_sylius.cart.';

    protected ?PasskeyCeremonyContext $passkeyCeremonyContext = null;

    /**
     * @param OrderRepositoryInterface<OrderInterface> $orderRepository
     * @param CustomerRepositoryInterface<CustomerInterface> $customerRepository
     * @param FactoryInterface<OrderInterface> $orderFactory
     * @param FactoryInterface<OrderItemInterface> $orderItemFactory
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     * @param ProductVariantRepositoryInterface<ProductVariantInterface> $productVariantRepository
     */
    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        protected CustomerRepositoryInterface $customerRepository,
        protected EntityManagerInterface $entityManager,
        protected SharedStorageInterface $sharedStorage,
        protected FactoryInterface $orderFactory,
        protected FactoryInterface $orderItemFactory,
        protected OrderItemQuantityModifierInterface $itemQuantityModifier,
        protected OrderProcessorInterface $orderProcessor,
        protected SessionFactoryInterface $sessionFactory,
        protected Session $minkSession,
        protected ChannelRepositoryInterface $channelRepository,
        protected ProductVariantRepositoryInterface $productVariantRepository,
    ) {
    }

    #[BeforeScenario]
    public function gatherPasskeyCeremonyContext(BeforeScenarioScope $scope): void
    {
        $environment = $scope->getEnvironment();
        $this->passkeyCeremonyContext = $environment instanceof InitializedSymfonyExtensionEnvironment
            && $environment->hasContextClass(PasskeyCeremonyContext::class)
            ? $environment->getContext(PasskeyCeremonyContext::class)
            : null;
    }

    /**
     * Sylius adds to the cart through a live component, which needs a real browser. The cart a
     * visitor builds that way is an order in the `cart` state whose ID the session holds per
     * channel; this puts one into a fresh session. The session cookie goes to the Mink browser and,
     * in a suite with the passkey ceremony, to the kernel browser that context drives.
     *
     * @Given I have a guest cart with this product
     */
    public function iHaveAGuestCartWithThisProduct(): void
    {
        // Reloaded: a kernel browser request reboots the kernel and detaches what the setup created.
        $storedChannel = $this->sharedStorage->get('channel');
        Assert::isInstanceOf($storedChannel, ChannelInterface::class);
        $channel = $this->channelRepository->find($storedChannel->getId());
        Assert::isInstanceOf($channel, ChannelInterface::class);
        $product = $this->sharedStorage->get('product');
        Assert::isInstanceOf($product, ProductInterface::class);
        $storedVariant = $product->getVariants()->first();
        Assert::isInstanceOf($storedVariant, ProductVariantInterface::class);
        $variant = $this->productVariantRepository->find($storedVariant->getId());
        Assert::isInstanceOf($variant, ProductVariantInterface::class);

        $cart = $this->orderFactory->createNew();
        $cart->setChannel($channel);
        $cart->setCurrencyCode((string) $channel->getBaseCurrency()?->getCode());
        $cart->setLocaleCode((string) $channel->getDefaultLocale()?->getCode());

        $item = $this->orderItemFactory->createNew();
        $item->setVariant($variant);
        $this->itemQuantityModifier->modify($item, 1);
        $cart->addItem($item);

        $this->orderProcessor->process($cart);
        $this->orderRepository->add($cart);

        $session = $this->sessionFactory->createSession();
        $session->start();
        $session->set(static::CART_SESSION_KEY_PREFIX . $channel->getCode(), $cart->getId());
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $this->passkeyCeremonyContext?->setCookie($cookie);
        $driver = $this->minkSession->getDriver();
        Assert::isInstanceOf($driver, BrowserKitDriver::class);
        $driver->getClient()->getCookieJar()->set($cookie);
    }

    /**
     * @Then my cart should belong to :email
     */
    public function myCartShouldBelongTo(string $email): void
    {
        $this->entityManager->clear();

        $carts = $this->orderRepository->findBy(['state' => OrderInterface::STATE_CART], ['id' => 'DESC'], 1);
        Assert::count($carts, 1, 'No cart was created.');
        $cart = $carts[0];

        Assert::greaterThan($cart->getItems()->count(), 0, 'The cart holds no items.');
        Assert::same($cart->getCustomer()?->getEmailCanonical(), strtolower($email), 'The cart does not belong to the customer.');
        Assert::false($cart->isCreatedByGuest(), 'The cart is still marked as created by a guest, so checkout would ask for an email.');
    }

    /**
     * @Then the guest customer :email should still have no account
     */
    public function theGuestCustomerShouldStillHaveNoAccount(string $email): void
    {
        $this->entityManager->clear();

        $customers = $this->customerRepository->findBy(['emailCanonical' => strtolower($email)]);
        Assert::count($customers, 1, sprintf('Expected exactly one customer "%s".', $email));
        Assert::null($customers[0]->getUser(), sprintf('An account was created on the guest customer "%s".', $email));
    }

    /**
     * @Then the account of :email should keep the order :number
     */
    public function theAccountShouldKeepTheOrder(string $email, string $number): void
    {
        $this->entityManager->clear();

        $customers = $this->customerRepository->findBy(['emailCanonical' => strtolower($email)]);
        Assert::count($customers, 1, sprintf('Expected exactly one customer "%s".', $email));
        $customer = $customers[0];
        Assert::isInstanceOf($customer, CustomerInterface::class);
        Assert::notNull($customer->getUser(), sprintf('Customer "%s" has no account.', $email));

        $numbers = array_map(
            static fn (OrderInterface $order): ?string => $order->getNumber(),
            $customer->getOrders()->toArray(),
        );
        Assert::inArray($number, $numbers, sprintf('Customer "%s" lost the order "%s".', $email, $number));
    }
}
