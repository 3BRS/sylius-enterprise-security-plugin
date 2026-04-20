<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;

interface TwoFactorVerifyTypeInterface
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void;

    public function getBlockPrefix(): string;
}
