<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Show;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Utility\Show\ObjectRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ObjectRule::class)]
#[Medium]
final class ObjectRuleTest extends TestCase
{
    public function testStatementLowersTheSchemaStatements(): void
    {
        self::assertSame('SHOW FULL TRIGGERS', (new Inspection())->inspect('show full triggers')->toString());
        self::assertSame('SHOW TABLE STATUS', (new Inspection())->inspect('show table status')->toString());
    }

    public function testDefinitionLowersShowCreate(): void
    {
        self::assertSame('SHOW CREATE DATABASE IF NOT EXISTS db', (new Inspection())->inspect('show create schema if not exists db')->toString());
        self::assertSame('SHOW FUNCTION STATUS', (new Inspection())->inspect('show function status')->toString());
    }

    public function testTableLowersTheName(): void
    {
        self::assertSame('SHOW CREATE VIEW db.v', (new Inspection())->inspect('show create view db.v')->toString());
    }
}
