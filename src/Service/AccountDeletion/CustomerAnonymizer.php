<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion;

use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;

class CustomerAnonymizer implements CustomerAnonymizerInterface
{
    protected const ANONYMIZED_FIRST_NAME = 'Deleted';

    protected const ANONYMIZED_LAST_NAME = 'User';

    protected const ANONYMIZED_EMAIL_DOMAIN = 'anonymized.invalid';

    protected const ANONYMIZED_STREET = 'Anonymized';

    protected const ANONYMIZED_CITY = 'Anonymized';

    protected const ANONYMIZED_POSTCODE = '00000';

    public function anonymize(CustomerInterface $customer): void
    {
        $anonymizedEmail = sprintf('deleted-%d@%s', (int) $customer->getId(), self::ANONYMIZED_EMAIL_DOMAIN);

        $customer->setFirstName(self::ANONYMIZED_FIRST_NAME);
        $customer->setLastName(self::ANONYMIZED_LAST_NAME);
        $customer->setEmail($anonymizedEmail);
        $customer->setEmailCanonical(strtolower($anonymizedEmail));
        $customer->setPhoneNumber(null);

        foreach ($customer->getAddresses() as $address) {
            $this->anonymizeAddress($address);
        }
    }

    protected function anonymizeAddress(AddressInterface $address): void
    {
        $address->setFirstName(self::ANONYMIZED_FIRST_NAME);
        $address->setLastName(self::ANONYMIZED_LAST_NAME);
        $address->setStreet(self::ANONYMIZED_STREET);
        $address->setCity(self::ANONYMIZED_CITY);
        $address->setPostcode(self::ANONYMIZED_POSTCODE);
        $address->setPhoneNumber(null);
    }
}
