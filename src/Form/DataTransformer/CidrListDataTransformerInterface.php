<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

/**
 * @extends DataTransformerInterface<list<string>, string>
 */
interface CidrListDataTransformerInterface extends DataTransformerInterface
{
}
