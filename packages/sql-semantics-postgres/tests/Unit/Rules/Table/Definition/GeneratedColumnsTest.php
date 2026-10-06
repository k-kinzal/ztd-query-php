<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\GeneratedColumns;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(GeneratedColumns::class)]
#[Medium]
final class GeneratedColumnsTest extends TestCase
{
    public function testReferencedFindsTheFirstGeneratedColumnInWrittenOrder(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $create = $semantics->analyze('CREATE TABLE t (a int, b int GENERATED ALWAYS AS (a) STORED, c int GENERATED ALWAYS AS (a) STORED, d int GENERATED ALWAYS AS (a + c + b) STORED)');
        self::assertEquals([new DefinitionProblem(DefinitionRule::GeneratedInGeneration, new Name('c'))], $create->facts->diagnostics);
    }

    public function testExpressionAcceptsRegularColumns(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-16.6');
        $create = $semantics->analyze('CREATE TABLE t (a int, b int GENERATED ALWAYS AS (a) STORED, c int DEFAULT 1, d int GENERATED ALWAYS AS (t.c + a) STORED)');
        self::assertSame([], $create->facts->diagnostics);
    }

    public function testExpressionReportsAnAddedColumnUsingAGeneratedColumn(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $table = $semantics->analyze('CREATE TABLE t (a int, b int GENERATED ALWAYS AS (a) STORED)');
        $alter = $semantics->analyze('ALTER TABLE t ADD COLUMN d int GENERATED ALWAYS AS (b + 1) STORED', $table->declarations());
        self::assertSame(['cannot use generated column "b" in column generation expression'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $alter->facts->diagnostics));
    }

    public function testPartitionKeyReportsAGeneratedColumnInAnExpression(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $create = $semantics->analyze('CREATE TABLE t (a int, b int GENERATED ALWAYS AS (a) STORED) PARTITION BY RANGE (a, (b + 1))');
        self::assertSame(['cannot use generated column in partition key'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    public function testAmongTellsAGeneratedReferencingColumn(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $context = [...$semantics->analyze('CREATE TABLE r (a int PRIMARY KEY)')->declarations(), ...$semantics->analyze('CREATE TABLE t (a int, b int GENERATED ALWAYS AS (a) STORED)')->declarations()];
        $alter = $semantics->analyze('ALTER TABLE t ADD FOREIGN KEY (a) REFERENCES r ON DELETE SET NULL, ADD FOREIGN KEY (b) REFERENCES r ON DELETE SET DEFAULT', $context);
        self::assertSame(['invalid ON DELETE action for foreign key constraint containing generated column'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $alter->facts->diagnostics));
    }

    public function testKeyActionsReportsOnUpdateFirst(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $table = $semantics->analyze('CREATE TABLE r (a int PRIMARY KEY)');
        $create = $semantics->analyze('CREATE TABLE t (a int, b int GENERATED ALWAYS AS (a) STORED, FOREIGN KEY (b) REFERENCES r ON DELETE SET NULL ON UPDATE CASCADE)', $table->declarations());
        self::assertSame(['invalid ON UPDATE action for foreign key constraint containing generated column'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    public function testColumnKeysReportsAReferencesConstraintOfAGeneratedColumn(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-16.6');
        $table = $semantics->analyze('CREATE TABLE r (a int PRIMARY KEY)');
        $create = $semantics->analyze('CREATE TABLE t (a int, b int GENERATED ALWAYS AS (a) STORED REFERENCES r ON UPDATE SET DEFAULT, c int REFERENCES r ON UPDATE SET NULL)', $table->declarations());
        self::assertSame(['invalid ON UPDATE action for foreign key constraint containing generated column'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    public function testInheritedReportsAGenerationConflictOfTwoParents(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $context = [...$semantics->analyze('CREATE TABLE p (a int, b int GENERATED ALWAYS AS (a) STORED)')->declarations(), ...$semantics->analyze('CREATE TABLE q (b int)')->declarations()];
        $create = $semantics->analyze('CREATE TABLE c () INHERITS (p, q)', $context);
        self::assertSame(['inherited column "b" has a generation conflict'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    public function testInheritedReportsTheOptionsOfAPartitionColumn(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $table = $semantics->analyze('CREATE TABLE p (a int, b int GENERATED ALWAYS AS (a) STORED) PARTITION BY LIST (a)');
        $create = $semantics->analyze('CREATE TABLE p1 PARTITION OF p (b DEFAULT 1) FOR VALUES IN (1)', $table->declarations());
        self::assertSame(['column "b" inherits from generated column but specifies default'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    public function testMergedReportsTheGenerationOfAColumnDefinition(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $table = $semantics->analyze('CREATE TABLE p (a int, b int GENERATED ALWAYS AS (a) STORED)');
        $create = $semantics->analyze('CREATE TABLE c (b int GENERATED ALWAYS AS IDENTITY, a int GENERATED ALWAYS AS (2) STORED) INHERITS (p)', $table->declarations());
        self::assertSame([
            'column "b" inherits from generated column but specifies identity',
            'child column "a" specifies generation expression',
        ], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }
}
