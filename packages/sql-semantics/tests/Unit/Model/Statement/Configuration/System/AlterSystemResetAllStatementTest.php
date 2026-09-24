<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration\System;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\System\AlterSystemResetAllStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterSystemResetAllStatement::class)]
#[Medium]
final class AlterSystemResetAllStatementTest extends TestCase
{
    public function testWithOriginRetainsTheFullReset(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SYSTEM RESET ALL');
        self::assertInstanceOf(AlterSystemResetAllStatement::class, $statement);
        self::assertSame(StatementKind::Alter, $statement->withOrigin($statement->origin)->kind);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin((new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin);
    }
}
