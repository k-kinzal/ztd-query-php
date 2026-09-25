<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Session\DiagnosticSelection;
use SqlSemantics\Model\Statement\Inspection\Session\ShowDiagnosticsStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowDiagnosticsStatement::class)]
#[Medium]
final class ShowDiagnosticsStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testResultColumnsListLevelCodeAndMessageAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind('SHOW ERRORS LIMIT 1, 3');
        self::assertInstanceOf(ShowDiagnosticsStatement::class, $statement);
        self::assertSame(DiagnosticSelection::Errors, $statement->selection);
        self::assertSame(['Level', 'Code', 'Message'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('integer', $statement->resultColumns()[1]->expression->type->name);
        self::assertSame('SHOW ERRORS LIMIT 3 OFFSET 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testWithSelectionReplacesTheConditionsImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW WARNINGS LIMIT 4');
        self::assertInstanceOf(ShowDiagnosticsStatement::class, $statement);
        $changed = $statement->withSelection(DiagnosticSelection::Errors);
        self::assertNotSame($statement, $changed);
        self::assertSame(DiagnosticSelection::Warnings, $statement->selection);
        self::assertSame('SHOW ERRORS LIMIT 4', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithLimitReplacesTheWindowImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('SHOW WARNINGS');
        $other = $binder->bind('SHOW ERRORS LIMIT ? OFFSET 2');
        self::assertInstanceOf(ShowDiagnosticsStatement::class, $statement);
        self::assertInstanceOf(ShowDiagnosticsStatement::class, $other);
        $changed = $statement->withLimit($other->limit);
        self::assertNotSame($statement, $changed);
        self::assertNull($statement->limit);
        self::assertSame('SHOW WARNINGS LIMIT ? OFFSET 2', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame('SHOW WARNINGS', (new \SqlSemantics\SimpleSerializer())->serialize($changed->withLimit(null)));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW WARNINGS LIMIT 4');
        self::assertInstanceOf(ShowDiagnosticsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame([$statement->selection, $statement->limit], [$copy->selection, $copy->limit]);
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        new ShowDiagnosticsStatement($statement->origin, DiagnosticSelection::Warnings);
    }
}
