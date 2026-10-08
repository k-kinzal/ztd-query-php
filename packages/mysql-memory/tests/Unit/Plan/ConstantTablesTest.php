<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use MySqlMemory\Instance;
use MySqlMemory\Plan\ConstantTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(ConstantTables::class)]
#[Small]
final class ConstantTablesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerJoinedCountsTheTablesThatAreNotConstant(): iterable
    {
        yield 'one table' => ['SELECT 1 FROM t', 1];
        yield 'two tables' => ['SELECT 1 FROM t AS x, u', 2];
        yield 'key equal to a value' => ['SELECT 1 FROM t, u WHERE u.id = 1', 1];
        yield 'unique key of not null columns' => ["SELECT 1 FROM t, u WHERE u.name = 'x'", 1];
        yield 'key equal to a constant table' => ['SELECT 1 FROM t JOIN u ON u.id = t.id WHERE t.id = 1', 0];
        yield 'inner side of an outer join' => ['SELECT 1 FROM t LEFT JOIN u ON u.id = 1', 2];
        yield 'derived table without tables' => ['SELECT 1 FROM t, (SELECT 1) AS y', 1];
        yield 'derived table of one row' => ['SELECT 1 FROM t, (SELECT id FROM u LIMIT 1) AS y', 1];
        yield 'derived table' => ['SELECT 1 FROM t, (SELECT id FROM u) AS y', 2];
        yield 'semijoin' => ['SELECT 1 FROM t WHERE id IN (SELECT id FROM u) AND NOT EXISTS (SELECT 1 FROM u)', 3];
    }

    #[DataProvider('providerJoinedCountsTheTablesThatAreNotConstant')]
    public function testJoinedCountsTheTablesThatAreNotConstant(string $sql, int $count): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY, a INT); CREATE TABLE u (id INT PRIMARY KEY, name VARCHAR(5) NOT NULL, UNIQUE KEY (name))');
        $operation = $session->analyze($sql);
        self::assertInstanceOf(Select::class, $operation->statement);

        self::assertSame($count, (new ConstantTables($operation->facts))->joined($operation->statement));
    }

    public function testTablesMarksTheInnerSideOfAnOuterJoin(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT)');
        $operation = $session->analyze('SELECT 1 FROM t AS a LEFT JOIN (t AS b, t AS c) ON 1');
        self::assertInstanceOf(Select::class, $operation->statement);

        self::assertSame([false, true, true], array_map(static fn (array $entry): bool => $entry[1], (new ConstantTables($operation->facts))->tables($operation->statement->from, false)));
    }

    public function testConstantsAnswersTheConstantTables(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY)');
        $operation = $session->analyze('SELECT 1 FROM t AS a, t AS b WHERE b.id = 2');
        self::assertInstanceOf(Select::class, $operation->statement);
        $tables = (new ConstantTables($operation->facts))->tables($operation->statement->from, false);

        self::assertSame([spl_object_id($tables[1][0])], array_keys((new ConstantTables($operation->facts))->constants($operation->statement, $tables)));
    }

    public function testSingleHoldsForAQueryOfOneRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT)');
        $operation = $session->analyze('SELECT 1');
        $constants = new ConstantTables($operation->facts);
        $limited = $session->analyze('(SELECT id FROM t) LIMIT 1')->statement;
        $offset = $session->analyze('SELECT id FROM t LIMIT 1 OFFSET 1')->statement;
        self::assertInstanceOf(\SqlSemantics\Statement\Query::class, $limited);
        self::assertInstanceOf(\SqlSemantics\Statement\Query::class, $offset);
        self::assertInstanceOf(\SqlSemantics\Statement\Query::class, $operation->statement);

        self::assertSame([true, true, false], [$constants->single($operation->statement), $constants->single($limited), $constants->single($offset)]);
    }

    public function testKeyedHoldsWhenEveryColumnOfAKeyIsBound(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT, b INT, PRIMARY KEY (id, b))');
        $operation = $session->analyze('SELECT 1 FROM t');
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(TableReference::class, $operation->statement->from);
        $table = $operation->facts->relation($operation->statement->from)->table;
        self::assertInstanceOf(DeclaredTable::class, $table);
        [$id, $b] = $table->table->columns;
        $constants = new ConstantTables($operation->facts);

        self::assertSame([false, true], [$constants->keyed($operation->statement->from, [spl_object_id($id) => true]), $constants->keyed($operation->statement->from, [spl_object_id($id) => true, spl_object_id($b) => true])]);
    }

    public function testColumnAnswersTheColumnAnExpressionNames(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT)');
        $operation = $session->analyze('SELECT (id), id + 1 FROM t');
        $first = $operation->field(0)->expression;
        $second = $operation->field(1)->expression;
        self::assertNotNull($first);
        self::assertNotNull($second);

        self::assertSame(['id', null], [(new ConstantTables($operation->facts))->column($first)?->slot->name?->value, (new ConstantTables($operation->facts))->column($second)]);
    }

    public function testFixedHoldsForAValueThatReadsOnlyConstantTables(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT)');
        $operation = $session->analyze('SELECT id + 1, 2 FROM t');
        $read = $operation->field(0)->expression;
        $value = $operation->field(1)->expression;
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertNotNull($operation->statement->from);
        self::assertNotNull($read);
        self::assertNotNull($value);
        $members = [spl_object_id($operation->statement->from) => true];
        $constants = new ConstantTables($operation->facts);

        self::assertSame([false, true, true], [$constants->fixed($read, $members, []), $constants->fixed($read, $members, $members), $constants->fixed($value, $members, [])]);
    }

    public function testConjunctsAnswersTheOperandsOfAnd(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1 WHERE (1 AND 2) AND 3 OR 4');
        self::assertInstanceOf(Select::class, $operation->statement);

        self::assertCount(1, (new ConstantTables($operation->facts))->conjuncts($operation->statement->where));
        self::assertCount(0, (new ConstantTables($operation->facts))->conjuncts(null));
    }

    public function testJoinsAnswersTheConditionsOfTheInnerJoins(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT)');
        $operation = $session->analyze('SELECT 1 FROM t AS a JOIN t AS b ON 1 AND 2 LEFT JOIN t AS c ON 3');
        self::assertInstanceOf(Select::class, $operation->statement);

        self::assertCount(2, (new ConstantTables($operation->facts))->joins($operation->statement->from));
    }

    public function testUnwrapRemovesTheParentheses(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT ((1))');
        $expression = $operation->field(0)->expression;
        self::assertNotNull($expression);

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral::class, (new ConstantTables($operation->facts))->unwrap($expression));
    }
}
