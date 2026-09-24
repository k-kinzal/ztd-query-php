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
use SqlSemantics\Model\Statement\Inspection\Session\ShowDiagnosticCountStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowDiagnosticCountStatement::class)]
#[Medium]
final class ShowDiagnosticCountStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testResultColumnsNameTheSessionCounterAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind('SHOW COUNT( * ) ERRORS');
        self::assertInstanceOf(ShowDiagnosticCountStatement::class, $statement);
        self::assertSame(DiagnosticSelection::Errors, $statement->selection);
        self::assertSame(['@@session.error_count'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('SHOW COUNT(*) ERRORS', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWithSelectionReplacesTheCounterImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW COUNT(*) ERRORS');
        self::assertInstanceOf(ShowDiagnosticCountStatement::class, $statement);
        $changed = $statement->withSelection(DiagnosticSelection::Warnings);
        self::assertNotSame($statement, $changed);
        self::assertSame(DiagnosticSelection::Errors, $statement->selection);
        self::assertSame('SHOW COUNT(*) WARNINGS', $changed->toString());
        self::assertSame(['@@session.warning_count'], array_column($changed->resultColumns(), 'name'));
    }

    public function testWithOriginRetainsTheSelection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW COUNT(*) WARNINGS');
        self::assertInstanceOf(ShowDiagnosticCountStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->selection, $copy->selection);
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        new ShowDiagnosticCountStatement($statement->origin, DiagnosticSelection::Errors);
    }
}
