<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Vacuum;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Vacuum::class)]
#[Medium]
final class VacuumTest extends TestCase
{
    public function testDeriveStatementDerivesTheTargetWhereNoRelationIsVisible(): void
    {
        $operation = (new Inspection())->diagnose('VACUUM INTO backup');

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[0]);
    }

    public function testDeriveStatementRecordsNothingWithoutATarget(): void
    {
        $operation = (new Inspection())->diagnose('VACUUM', []);

        self::assertNull($operation->shape());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testIgnoresDestinationForTheCaseEquivalentTemporaryName(): void
    {
        self::assertTrue((new Vacuum(new Name('TeMp')))->ignoresDestination());
        self::assertFalse((new Vacuum(new Name('main')))->ignoresDestination());
        self::assertFalse((new Vacuum())->ignoresDestination());
    }

    public function testRenderWritesTheSchemaAndTheTarget(): void
    {
        $operation = (new Inspection())->diagnose("vacuum main into 'backup.db'");

        self::assertInstanceOf(Vacuum::class, $operation->statement);
        self::assertSame('main', $operation->statement->schema?->value);
        self::assertSame("VACUUM main INTO 'backup.db'", $operation->toString());
    }
}
