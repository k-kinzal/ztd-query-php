<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\GeneratedChanges;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(GeneratedChanges::class)]
#[Medium]
final class GeneratedChangesTest extends TestCase
{
    public function testCheckReportsADefaultOfAGeneratedColumn(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $table = $semantics->analyze('CREATE TABLE t (a int, b int GENERATED ALWAYS AS (a) STORED)');
        $alter = $semantics->analyze('ALTER TABLE t ALTER COLUMN a SET DEFAULT 1, ALTER COLUMN b SET DEFAULT 1', $table->declarations());
        self::assertEquals([new DefinitionProblem(DefinitionRule::GeneratedColumnDefault, new Name('b'), new Name('t'))], $alter->facts->diagnostics);
    }

    public function testCheckReportsSetExpressionOfARegularColumn(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $table = $semantics->analyze('CREATE TABLE t (a int, b int GENERATED ALWAYS AS (a) STORED)');
        $alter = $semantics->analyze('ALTER TABLE t ALTER COLUMN a SET EXPRESSION AS (1), ALTER COLUMN b SET EXPRESSION AS (a + 1)', $table->declarations());
        self::assertSame(['column "a" of relation "t" is not a generated column'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $alter->facts->diagnostics));
    }

    public function testCheckReportsUsingForAGeneratedColumn(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-16.6');
        $table = $semantics->analyze('CREATE TABLE t (a int, b int GENERATED ALWAYS AS (a) STORED)');
        $alter = $semantics->analyze('ALTER TABLE t ALTER COLUMN a TYPE bigint USING a, ALTER COLUMN b TYPE bigint USING b', $table->declarations());
        self::assertSame(['cannot specify USING when altering type of generated column'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $alter->facts->diagnostics));
    }

    public function testRuleReportsDropExpressionOfARegularColumn(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-16.6');
        $table = $semantics->analyze('CREATE TABLE t (a int, b int GENERATED ALWAYS AS (a) STORED)');
        $alter = $semantics->analyze('ALTER TABLE t ALTER COLUMN a DROP EXPRESSION, ALTER COLUMN a DROP EXPRESSION IF EXISTS, ALTER COLUMN b DROP EXPRESSION', $table->declarations());
        self::assertSame(['column "a" of relation "t" is not a stored generated column'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $alter->facts->diagnostics));
    }

    public function testRuleReportsDropDefaultOfAGeneratedColumn(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $table = $semantics->analyze('CREATE TABLE t (a int, b int GENERATED ALWAYS AS (a) STORED)');
        $alter = $semantics->analyze('ALTER TABLE t ALTER COLUMN b DROP DEFAULT', $table->declarations());
        self::assertSame(['column "b" of relation "t" is a generated column'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $alter->facts->diagnostics));
    }

    public function testGeneratedIsUnknownForATableTheContextLacks(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $alter = $semantics->analyze('ALTER TABLE zz ALTER COLUMN a DROP EXPRESSION, ALTER COLUMN b SET DEFAULT 1');
        self::assertSame([], $alter->facts->diagnostics);
    }

    public function testDroppedAcceptsADefaultAfterTheExpressionIsDropped(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $table = $semantics->analyze('CREATE TABLE t (a int, b int GENERATED ALWAYS AS (a) STORED)');
        $alter = $semantics->analyze('ALTER TABLE t ALTER COLUMN b SET DEFAULT 1, ALTER COLUMN b DROP EXPRESSION', $table->declarations());
        self::assertSame([], $alter->facts->diagnostics);
    }
}
