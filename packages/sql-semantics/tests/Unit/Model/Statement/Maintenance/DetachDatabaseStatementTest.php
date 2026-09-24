<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Maintenance\DetachDatabaseStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DetachDatabaseStatement::class)]
#[Medium]
final class DetachDatabaseStatementTest extends TestCase
{
    public function testBindsTheSchemaOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('DETACH archive', strict: false);
        self::assertInstanceOf(DetachDatabaseStatement::class, $statement);
        self::assertSame('archive', $statement->schema->spelling());
        self::assertSame(StatementKind::Detach, $statement->kind);
        self::assertSame('DETACH DATABASE "archive"', $statement->toString());
    }

    public function testWithOriginPreservesTheSchema(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('DETACH DATABASE archive', strict: false);
        self::assertInstanceOf(DetachDatabaseStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::Sqlite));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->schema, $copy->schema);
        self::assertSame($statement->toString(), $copy->toString());
    }
}
