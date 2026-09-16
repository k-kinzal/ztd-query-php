<?php

declare(strict_types=1);

namespace Tests\Unit;

use Container\Pdo\MySqlContainer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MySqlContainer::class)]
final class MySqlContainerTest extends TestCase
{
    public function testDefaultsToPinnedVersionWithoutEnvironmentOverride(): void
    {
        $previous = getenv('MYSQL_VERSION');
        try {
            putenv('MYSQL_VERSION');
            self::assertSame('mysql:8.0.44', (new MySqlContainer())->image());
        } finally {
            putenv($previous === false ? 'MYSQL_VERSION' : 'MYSQL_VERSION=' . $previous);
        }
    }

    public function testVersionOverrideAppliesOnlyToNewInstances(): void
    {
        $previous = getenv('MYSQL_VERSION');
        try {
            putenv('MYSQL_VERSION=8.4.7');
            $mysql84 = new MySqlContainer();
            putenv('MYSQL_VERSION=8.0.44');
            $mysql80 = new MySqlContainer();

            self::assertSame('mysql:8.4.7', $mysql84->image());
            self::assertSame('mysql:8.0.44', $mysql80->image());
        } finally {
            putenv($previous === false ? 'MYSQL_VERSION' : 'MYSQL_VERSION=' . $previous);
        }
    }
}
