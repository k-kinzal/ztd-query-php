<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\OptimizerHints;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(OptimizerHints::class)]
#[Medium]
final class OptimizerHintsTest extends TestCase
{
    public function testBindReadsEveryDirectiveFromTheHintComments(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('SELECT /*+ MAX_EXECUTION_TIME(1000) MAX_EXECUTION_TIME(2) */ /*+ MAX_EXECUTION_TIME(3) */ a FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertCount(3, $statement->hints);
        self::assertContainsOnlyInstancesOf(\SqlSemantics\Model\Query\Optimization\MaxExecutionTime::class, $statement->hints);
        self::assertSame(['1000', '2', '3'], array_column($statement->hints, 'milliseconds'));
        self::assertSame('SELECT /*+ MAX_EXECUTION_TIME(1000) MAX_EXECUTION_TIME(2) MAX_EXECUTION_TIME(3) */ `a` AS `a` FROM `t`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testBindIgnoresOrdinaryCommentsAndSelectsWithoutHints(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)'));
        $plain = $binder->bind('SELECT /* MAX_EXECUTION_TIME(1) */ a FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $plain);
        self::assertSame([], $plain->hints);
        self::assertSame([], OptimizerHints::bind($binder->bind('SELECT a FROM t')->origin->source));
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerBindReadsEveryExecutionTimeHint')]
    public function testBindReadsEveryExecutionTimeHint(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerBindReadsEveryExecutionTimeHint(): iterable
    {
        return [
            'select /*+ max_execution_time(10) */ a from t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'select /*+ max_execution_time(10) */ a from t', 'SELECT /*+ MAX_EXECUTION_TIME(10) */ `a` AS `a` FROM `t`'],
            'SELECT /*+ MAX_EXECUTION_TIME ( 5 ) MAX_EXECUTION_TIME(6) */ a FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'SELECT /*+ MAX_EXECUTION_TIME ( 5 ) MAX_EXECUTION_TIME(6) */ a FROM t', 'SELECT /*+ MAX_EXECUTION_TIME(5) MAX_EXECUTION_TIME(6) */ `a` AS `a` FROM `t`'],
            'SELECT /*+ MAX_EXECUTION_TIME(5) */ /*+ MAX_EXECUTION_TIME(7) */ 1 (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT)'], 'SELECT /*+ MAX_EXECUTION_TIME(5) */ /*+ MAX_EXECUTION_TIME(7) */ 1', 'SELECT /*+ MAX_EXECUTION_TIME(5) MAX_EXECUTION_TIME(7) */ 1'],
        ];
    }
}
