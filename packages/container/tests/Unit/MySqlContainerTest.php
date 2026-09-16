<?php

declare(strict_types=1);

namespace Tests\Unit;

use Container\MySql80Container;
use Container\MySql84Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MySql80Container::class)]
#[CoversClass(MySql84Container::class)]
final class MySqlContainerTest extends TestCase
{
    public function testVersionSelectionIsIndependentOfTheCallingEnvironment(): void
    {
        $previous = getenv('MYSQL_VERSION');
        try {
            putenv('MYSQL_VERSION=8.4.7');
            self::assertSame('mysql:8.0.44', (new MySql80Container())->image());
            self::assertSame('mysql-8.0.44', MySql80Container::getGrammarVersion());
            putenv('MYSQL_VERSION=8.0.44');
            self::assertSame('container-registry.oracle.com/mysql/community-server:8.4.7', (new MySql84Container())->image());
            self::assertSame('mysql-8.4.7', MySql84Container::getGrammarVersion());
        } finally {
            putenv($previous === false ? 'MYSQL_VERSION' : 'MYSQL_VERSION=' . $previous);
        }
    }
}
