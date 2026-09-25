<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Maintenance\VacuumDatabaseStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(VacuumDatabaseStatement::class)]
#[Medium]
final class VacuumDatabaseStatementTest extends TestCase
{
    public function testBindsAnOptionalSchema(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $whole = $binder->bind('VACUUM');
        $named = $binder->bind('VACUUM main');
        self::assertInstanceOf(VacuumDatabaseStatement::class, $whole);
        self::assertInstanceOf(VacuumDatabaseStatement::class, $named);
        self::assertNull($whole->schema);
        self::assertSame('main', $named->schema);
        self::assertSame(StatementKind::Vacuum, $named->kind);
        self::assertSame('VACUUM', (new \SqlSemantics\SimpleSerializer())->serialize($whole));
        self::assertSame('VACUUM "main"', (new \SqlSemantics\SimpleSerializer())->serialize($named));
    }

    public function testWithOriginPreservesTheSchema(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('VACUUM main');
        self::assertInstanceOf(VacuumDatabaseStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::Sqlite));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame('main', $copy->schema);
        self::assertSame('VACUUM "main"', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }
}
