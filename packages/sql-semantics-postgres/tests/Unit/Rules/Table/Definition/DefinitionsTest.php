<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Definitions::class)]
#[Medium]
final class DefinitionsTest extends TestCase
{
    public function testDeriveProvidesTheDeclaration(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->diagnose('CREATE TABLE n (x int CHECK (x > 0))', $context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $statement->facts->relation($n1)->table;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\DeclaredTable::class, $n2);
        self::assertSame(true, $n2->table === $statement->declarations()[0]);
    }

    public function testFactDoesNotProvideTheDeclaration(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('CREATE TABLE n (x int)', []);
        $derivation = new \SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation($statement->context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        self::assertSame('1 0', count((new \SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Definitions())->fact($n1, $derivation, null)->shape->slots) . ' ' . count($derivation->facts()->declarations));
    }

    public function testNameIsTemporaryForATemporaryTable(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('CREATE TEMP TABLE n (x int)', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        self::assertSame('pg_temp', (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Definitions())->name($n1, null)->schema?->value);
    }

    public function testElementsResolvesTheParents(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->diagnose('CREATE TABLE n () INHERITS (t, zz)', $context);
        self::assertSame(1, count($statement->facts->diagnostics));
    }

    public function testReportReportsRepeatedAndSystemColumnNames(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('CREATE TABLE n (a int, a int, xmin int, b serial[], PRIMARY KEY (a), PRIMARY KEY (b))', []);
        self::assertSame([
          0 => 'column "a" specified more than once',
          1 => 'column name "xmin" conflicts with a system column name',
          2 => 'array of serial is not implemented',
          3 => 'multiple primary keys for table "n" are not allowed',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testReportCountsTheColumnsALikeClauseCopies(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = $semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int)', [])->declarations();
        $statement = $semantics->diagnose('CREATE TABLE n (b text, LIKE t, c int)', $context);
        self::assertSame(['column "b" specified more than once'], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testDeriveReportsATemporaryTableInAPermanentSchema(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('CREATE TEMP TABLE public.n (a int)', []);
        self::assertSame(['cannot create temporary relation in non-temporary schema'], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testKindDeclaresAForeignTableAsOne(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        self::assertSame(
            [\SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\RelationKind::ForeignTable, \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\RelationKind::BaseTable],
            [$semantics->diagnose('CREATE FOREIGN TABLE f (a int) SERVER x')->declarations()[0]->kind, $semantics->diagnose('CREATE TABLE t (a int)')->declarations()[0]->kind],
        );
    }
}
