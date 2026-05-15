<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Settings;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\SecuritySetting;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\SecuritySettingRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsProviderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsWriter;

#[CoversClass(SettingsWriter::class)]
class SettingsWriterTest extends TestCase
{
    public function testCreatesNewSettingWhenNoneExists(): void
    {
        $repository = $this->createStub(SecuritySettingRepositoryInterface::class);
        $repository->method('findOneByPathAndScope')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(SecuritySetting::class));

        $provider = $this->createStub(SettingsProviderInterface::class);

        $writer = new SettingsWriter($repository, $em, $provider);
        $writer->set('password_policy.min_length', SettingsScope::CUSTOMER, 12);
    }

    public function testUpdatesExistingSetting(): void
    {
        $existing = new SecuritySetting();
        $existing->setPath('password_policy.min_length');
        $existing->setScope(SettingsScope::CUSTOMER->value);
        $existing->setValue(8);

        $repository = $this->createStub(SecuritySettingRepositoryInterface::class);
        $repository->method('findOneByPathAndScope')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $provider = $this->createStub(SettingsProviderInterface::class);

        $writer = new SettingsWriter($repository, $em, $provider);
        $writer->set('password_policy.min_length', SettingsScope::CUSTOMER, 14);

        self::assertSame(14, $existing->getValue());
    }

    public function testFlushPersistsAndRefreshesProvider(): void
    {
        $repository = $this->createStub(SecuritySettingRepositoryInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $provider = $this->createMock(SettingsProviderInterface::class);
        $provider->expects(self::once())->method('refresh');

        $writer = new SettingsWriter($repository, $em, $provider);
        $writer->flush();
    }

    public function testSetManyAppliesAllValues(): void
    {
        $repository = $this->createStub(SecuritySettingRepositoryInterface::class);
        $repository->method('findOneByPathAndScope')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(3))->method('persist');

        $provider = $this->createStub(SettingsProviderInterface::class);

        $writer = new SettingsWriter($repository, $em, $provider);
        $writer->setMany(SettingsScope::ADMIN, [
            'password_policy.min_length' => 16,
            'password_policy.require_uppercase' => true,
            'password_history.enabled' => true,
        ]);
    }
}
