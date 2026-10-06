<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Show;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Utility\Show\ObjectRule;

#[CoversClass(ObjectRule::class)]
#[Medium]
final class ObjectRuleTest extends TestCase
{
    public function testStatementLowersTheSchemaStatements(): void
    {
        self::assertSame('SHOW FULL TRIGGERS', (new Semantics(Dialect::MySql))->analyze('show full triggers')->toString());
        self::assertSame('SHOW TABLE STATUS', (new Semantics(Dialect::MySql))->analyze('show table status')->toString());
    }

    public function testDefinitionLowersShowCreate(): void
    {
        self::assertSame('SHOW CREATE DATABASE IF NOT EXISTS db', (new Semantics(Dialect::MySql))->analyze('show create schema if not exists db')->toString());
        self::assertSame('SHOW FUNCTION STATUS', (new Semantics(Dialect::MySql))->analyze('show function status')->toString());
    }

    public function testTableLowersTheName(): void
    {
        self::assertSame('SHOW CREATE VIEW db.v', (new Semantics(Dialect::MySql))->analyze('show create view db.v')->toString());
    }
}
