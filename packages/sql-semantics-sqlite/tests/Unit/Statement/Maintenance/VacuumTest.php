<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Vacuum;
use SqlSemantics\Statement\Reference\Column\MissingColumn;

#[CoversClass(Vacuum::class)]
#[Medium]
final class VacuumTest extends TestCase
{
    public function testDeriveStatementDerivesTheTargetWhereNoRelationIsVisible(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('VACUUM INTO backup');

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[0]);
    }

    public function testDeriveStatementRecordsNothingWithoutATarget(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('VACUUM', []);

        self::assertNull($operation->shape());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesTheSchemaAndTheTarget(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("vacuum main into 'backup.db'");

        self::assertInstanceOf(Vacuum::class, $operation->statement);
        self::assertSame('main', $operation->statement->schema?->value);
        self::assertSame("VACUUM main INTO 'backup.db'", $operation->toString());
    }
}
