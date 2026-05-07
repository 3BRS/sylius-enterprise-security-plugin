<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Service\AccountDeletion;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion\CustomerAnonymizer;

#[CoversClass(CustomerAnonymizer::class)]
class CustomerAnonymizerTest extends TestCase
{
    public function testAnonymizesCustomerNameEmailAndPhone(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(42);
        $customer->method('getAddresses')->willReturn(new ArrayCollection());

        $customer->expects(self::once())->method('setFirstName')->with('Deleted');
        $customer->expects(self::once())->method('setLastName')->with('User');
        $customer->expects(self::once())->method('setEmail')->with('deleted-42@anonymized.invalid');
        $customer->expects(self::once())->method('setEmailCanonical')->with('deleted-42@anonymized.invalid');
        $customer->expects(self::once())->method('setPhoneNumber')->with(null);

        (new CustomerAnonymizer())->anonymize($customer);
    }

    public function testAnonymizesEachAddressInTheCustomersAddressBook(): void
    {
        $address1 = $this->createMock(AddressInterface::class);
        $address1->expects(self::once())->method('setFirstName')->with('Deleted');
        $address1->expects(self::once())->method('setLastName')->with('User');
        $address1->expects(self::once())->method('setStreet')->with('Anonymized');
        $address1->expects(self::once())->method('setCity')->with('Anonymized');
        $address1->expects(self::once())->method('setPostcode')->with('00000');
        $address1->expects(self::once())->method('setPhoneNumber')->with(null);

        $address2 = $this->createMock(AddressInterface::class);
        $address2->expects(self::once())->method('setFirstName')->with('Deleted');

        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getId')->willReturn(7);
        $customer->method('getAddresses')->willReturn(new ArrayCollection([$address1, $address2]));

        (new CustomerAnonymizer())->anonymize($customer);
    }
}
