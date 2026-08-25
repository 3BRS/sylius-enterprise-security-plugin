<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Settings;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\SecuritySetting;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\SecuritySettingRepositoryInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
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

        // Counting the persist() calls says three rows were written, not which. The
        // entities are built inside setMany(), so they have to be captured to tell a
        // correct write from one that stored the wrong path, scope or value.
        // persist() runs before setValue(), so the entities are collected here and
        // read after setMany() returns — reading them inside the callback would only
        // ever see a null value.
        $written = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(3))->method('persist')->willReturnCallback(
            function (object $entity) use (&$written): void {
                self::assertInstanceOf(SecuritySetting::class, $entity);
                $written[] = $entity;
            },
        );

        $provider = $this->createStub(SettingsProviderInterface::class);

        $writer = new SettingsWriter($repository, $em, $provider);
        $writer->setMany(SettingsScope::ADMIN, [
            'password_policy.min_length' => 16,
            'password_policy.require_uppercase' => true,
            'password_history.enabled' => true,
        ]);

        $stored = [];
        foreach ($written as $setting) {
            $stored[$setting->getPath()] = [$setting->getScope(), $setting->getValue()];
        }

        self::assertSame([
            'password_policy.min_length' => [SettingsScope::ADMIN->value, 16],
            'password_policy.require_uppercase' => [SettingsScope::ADMIN->value, true],
            'password_history.enabled' => [SettingsScope::ADMIN->value, true],
        ], $stored);
    }
}
