<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type;

interface TwoFactorVerifyTypeInterface
{
    public function getBlockPrefix(): string;
}
