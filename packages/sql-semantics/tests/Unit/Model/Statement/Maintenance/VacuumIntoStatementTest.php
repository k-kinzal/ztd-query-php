<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Maintenance\VacuumIntoStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(VacuumIntoStatement::class)]
#[Medium]
final class VacuumIntoStatementTest extends TestCase
{
    public function testBindsTheDestinationAndOptionalSchema(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $whole = $binder->bind("VACUUM INTO 'copy.db'");
        $named = $binder->bind("VACUUM main INTO 'copy.db'");
        self::assertInstanceOf(VacuumIntoStatement::class, $whole);
        self::assertInstanceOf(VacuumIntoStatement::class, $named);
        self::assertNull($whole->schema);
        self::assertSame('main', $named->schema);
        self::assertSame("'copy.db'", $named->destination->spelling());
        self::assertSame(StatementKind::Vacuum, $named->kind);
        self::assertSame("VACUUM INTO 'copy.db'", (new \SqlSemantics\SimpleSerializer())->serialize($whole));
        self::assertSame('VACUUM "main" INTO \'copy.db\'', (new \SqlSemantics\SimpleSerializer())->serialize($named));
    }

    public function testWithOriginPreservesTheDestination(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind("VACUUM main INTO 'copy.db'");
        self::assertInstanceOf(VacuumIntoStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::Sqlite));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->destination, $copy->destination);
        self::assertSame('main', $copy->schema);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }
}
