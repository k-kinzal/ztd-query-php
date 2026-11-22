<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Declarations::class)]
#[Medium]
final class DeclarationsTest extends TestCase
{
    public function testTableDeclaresTheColumnsOfAPartition(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->diagnose('CREATE TABLE p1 PARTITION OF t (b NOT NULL) DEFAULT', $context);
        self::assertSame([
          0 => 'a integer NotNull',
          1 => 'b integer NotNull',
          2 => 'c text Nullable',
        ], array_map(static fn ($column): string => $column->name->value . ' ' . $column->type->name() . ' ' . $column->nullability->name, $statement->declarations()[0]->columns));
    }

    public function testListedPutsInheritedColumnsFirst(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->diagnose('CREATE TABLE n (x int, b int NOT NULL) INHERITS (t)', $context);
        self::assertSame([
          0 => 'a integer NotNull',
          1 => 'b integer NotNull',
          2 => 'c text Nullable',
          3 => 'x integer Nullable',
        ], array_map(static fn ($column): string => $column->name->value . ' ' . $column->type->name() . ' ' . $column->nullability->name, $statement->declarations()[0]->columns));
    }

    public function testCopyClosesTheDeclarationAtAnUndeclaredSource(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->diagnose('CREATE TABLE n (x int, LIKE zz, y int)', $context);
        self::assertSame([
          0 =>
           [
            0 => 'x integer Nullable',
          ],
          1 => false,
        ], [array_map(static fn ($column): string => $column->name->value . ' ' . $column->type->name() . ' ' . $column->nullability->name, $statement->declarations()[0]->columns), $statement->declarations()[0]->complete]);
    }

    public function testConstrainAppliesPrimaryKeys(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->diagnose('CREATE TABLE n (x int, y int, PRIMARY KEY (y))', $context);
        self::assertSame([
          0 => 'x integer Nullable',
          1 => 'y integer NotNull',
        ], array_map(static fn ($column): string => $column->name->value . ' ' . $column->type->name() . ' ' . $column->nullability->name, $statement->declarations()[0]->columns));
    }

    public function testNotNullCountsIdentityColumns(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->diagnose('CREATE TABLE n (x int GENERATED ALWAYS AS IDENTITY)', $context);
        self::assertSame([
          0 => 'x integer NotNull',
        ], array_map(static fn ($column): string => $column->name->value . ' ' . $column->type->name() . ' ' . $column->nullability->name, $statement->declarations()[0]->columns));
    }

    public function testCopyKeepsGeneratedColumnsOfParentsAndPartitions(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int, b int GENERATED ALWAYS AS (a) STORED) PARTITION BY LIST (a)')->declarations());
        $child = $semantics->diagnose('CREATE TABLE c (b int, x int) INHERITS (t)', $context);
        $partition = $semantics->diagnose('CREATE TABLE p1 PARTITION OF t FOR VALUES IN (1)', $context);
        self::assertSame(
            [[false, true, false], [false, true]],
            [array_map(static fn ($column): bool => $column->generated, $child->declarations()[0]->columns), array_map(static fn ($column): bool => $column->generated, $partition->declarations()[0]->columns)],
        );
    }

    public function testIncludesGeneratedAppliesTheOptionsInOrder(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int, b int GENERATED ALWAYS AS (a) STORED)')->declarations());
        self::assertSame(
            [[false, false], [false, true], [false, true], [false, false]],
            array_map(
                static fn (string $sql): array => array_map(static fn ($column): bool => $column->generated, $semantics->diagnose($sql, $context)->declarations()[0]->columns),
                ['CREATE TABLE l (LIKE t)', 'CREATE TABLE l (LIKE t INCLUDING GENERATED)', 'CREATE TABLE l (LIKE t EXCLUDING ALL INCLUDING ALL)', 'CREATE TABLE l (LIKE t INCLUDING GENERATED EXCLUDING ALL)'],
            ),
        );
    }

    public function testGeneratedTellsAGenerationClause(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('CREATE TABLE n (a int, b int GENERATED ALWAYS AS (a) STORED, c int GENERATED ALWAYS AS IDENTITY)');
        self::assertSame([false, true, false], array_map(static fn ($column): bool => $column->generated, $statement->declarations()[0]->columns));
    }

    public function testIdentityDistinguishesExplicitInheritancePartitionAndCopy(): void
    {
        $inspection = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $parent = $inspection->diagnose('CREATE TABLE t (n integer GENERATED ALWAYS AS IDENTITY) PARTITION BY LIST (n)')->declarations();
        $ordinary = $inspection->diagnose('CREATE TABLE o (n integer GENERATED BY DEFAULT AS IDENTITY)')->declarations();
        $partition = $inspection->diagnose('CREATE TABLE p PARTITION OF t FOR VALUES IN (1)', $parent)->declarations()[0];
        $inherited = $inspection->diagnose('CREATE TABLE c () INHERITS (o)', $ordinary)->declarations()[0];
        $withCopy = $inspection->diagnose('CREATE TABLE l (LIKE o INCLUDING IDENTITY)', $ordinary)->declarations()[0];
        $withoutCopy = $inspection->diagnose('CREATE TABLE m (LIKE o INCLUDING ALL EXCLUDING IDENTITY)', $ordinary)->declarations()[0];

        self::assertTrue($ordinary[0]->columns[0]->identity);
        self::assertTrue($partition->columns[0]->identity);
        self::assertFalse($inherited->columns[0]->identity);
        self::assertTrue($withCopy->columns[0]->identity);
        self::assertFalse($withoutCopy->columns[0]->identity);
    }
}
