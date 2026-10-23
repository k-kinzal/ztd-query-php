<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Explain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier;

#[CoversClass(Explain::class)]
#[Medium]
final class ExplainTest extends TestCase
{
    public function testDeriveStatementInspectsTheStatement(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $explain = $semantics->analyze('EXPLAIN FORMAT = JSON SELECT a FROM t', [$table]);
        self::assertInstanceOf(Explain::class, $explain->statement);
        self::assertSame([], $explain->facts->diagnostics);
        self::assertSame('EXPLAIN', $explain->field(0)->name?->value);
        self::assertSame([], $explain->declarations());
    }

    public function testDeriveStatementSearchesTheDatabaseOfForDatabase(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE shop.t (a INT)');
        $explain = $semantics->analyze('EXPLAIN FORMAT = TREE FOR DATABASE shop SELECT a FROM t', [$table]);
        self::assertInstanceOf(Explain::class, $explain->statement);
        self::assertSame([], $explain->facts->diagnostics);
        self::assertSame('shop', $explain->statement->database?->value);
    }

    public function testDeriveStatementReportsARefusedFormat(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage("This version of MySQL doesn't yet support 'EXPLAIN ANALYZE with TRADITIONAL format'");
        (new Semantics(Dialect::MySql))->analyze('EXPLAIN ANALYZE FORMAT = traditional SELECT 1');
    }

    public function testDeriveStatementStoresThePlanInto(): void
    {
        $explain = (new Semantics(Dialect::MySql))->analyze('EXPLAIN FORMAT = JSON INTO @plan SELECT 1');
        self::assertNull($explain->shape());
        self::assertSame([], $explain->facts->diagnostics);
    }

    public function testRenderWritesTheOptions(): void
    {
        self::assertSame('EXPLAIN EXTENDED SELECT 1', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('desc extended select 1')->toString());
        self::assertSame('EXPLAIN FORMAT = `JSON` INTO @v SELECT 1', (new Semantics(Dialect::MySql, 'mysql-8.1.0'))->analyze('explain format = JSON into @v select 1')->toString());
    }

    public function testRefusesAModifierWithOptions(): void
    {
        $select = (new Semantics(Dialect::MySql))->analyze('SELECT 1')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Explainable::class, $select);
        $this->expectExceptionMessage('EXTENDED and PARTITIONS are written alone.');
        new Explain($select, null, true, ExplainModifier::Extended);
    }
    public function testRefusesAnIntoWithoutExplicitJsonFormat(): void
    {
        $query = (new Semantics(Dialect::MySql))->analyze('SELECT 1')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Explainable::class, $query);
        $this->expectExceptionMessage('EXPLAIN INTO has an explicit JSON format.');
        new Explain($query, into: new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name('p')));
    }


    /**
     * @return iterable<string, array{string}>
     */
    public static function providerInvalidTargets(): iterable
    {
        yield 'empty' => [''];
        yield '65 ASCII characters' => [str_repeat('a', 65)];
        yield '65 multibyte characters' => [str_repeat('é', 65)];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidTargets')]
    public function testRefusesAnIntrinsicTargetName(string $name): void
    {
        $query = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]));
        $this->expectExceptionMessage('An EXPLAIN INTO target has between 1 and 64 characters.');
        new Explain($query, new \SqlSemantics\Statement\Identifier\Name('JSON'), into: new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name($name)));
    }
    public function testRefusesAnAnalysisFormatWithoutAStandaloneInterpretation(): void
    {
        $query = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]));
        $this->expectExceptionMessage('EXPLAIN ANALYZE cannot use the TRADITIONAL format.');
        new Explain($query, new \SqlSemantics\Statement\Identifier\Name('TRADITIONAL'), true);
    }

    public function testRefusesAnIgnoredDestinationAroundALaterTableSamplingClause(): void
    {
        $query = (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('SELECT id FROM t TABLESAMPLE SYSTEM(50)')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Explainable::class, $query);
        $this->expectExceptionMessage('The retained operands must share a supported MySQL release.');
        new Explain($query, emptyInto: true);
    }
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerOversizedDatabases(): iterable
    {
        yield 'ASCII' => [str_repeat('a', 65)];
        yield 'multibyte' => [str_repeat('猫', 65)];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerOversizedDatabases')]
    public function testRefusesAnOversizedDatabaseEvenBeforeAnExplainedOperandRuns(string $name): void
    {
        $query = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]));
        $this->expectExceptionMessage('An EXPLAIN database name has at most 64 characters.');
        new Explain($query, database: new \SqlSemantics\Statement\Identifier\Name($name));
    }

    public function testDeriveStatementKeepsAFullMultibyteDatabaseAtTheCharacterLimit(): void
    {
        $database = str_repeat('猫', 64);
        $explain = (new Semantics(Dialect::MySql))->analyze('EXPLAIN FOR SCHEMA `' . $database . '` SELECT 1')->statement;
        self::assertInstanceOf(Explain::class, $explain);
        self::assertSame($database, $explain->database?->value);
    }

}
