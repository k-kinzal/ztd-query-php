<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Server\ServerRules;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ServerRules::class)]
#[Medium]
final class ServerRulesTest extends TestCase
{
    public function testStatementLowersAStatementOfTheFamily(): void
    {
        self::assertSame('FLUSH STATUS', (new Inspection())->inspect('flush status')->toString());
    }

    public function testDefinitionLowersARoutedDefinition(): void
    {
        self::assertSame('CREATE DATABASE d', (new Inspection('mysql-5.6.51'))->inspect('create database d')->toString());
    }

    public function testCheckOptionsLowersTheOptionsOfCheckPartition(): void
    {
        self::assertSame('ALTER TABLE t CHECK PARTITION p QUICK FAST', (new Inspection())->inspect('alter table t check partition p quick fast')->toString());
    }

    public function testRepairOptionsLowersTheOptionsOfRepairPartition(): void
    {
        self::assertSame('ALTER TABLE t REPAIR PARTITION p USE_FRM', (new Inspection('mysql-5.7.44'))->inspect('alter table t repair partition p use_frm')->toString());
    }
}
