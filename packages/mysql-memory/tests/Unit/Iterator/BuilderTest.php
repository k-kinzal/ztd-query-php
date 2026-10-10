<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator;

use MySqlMemory\Evaluation\Aggregate\Accumulation;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Window\WindowFrame;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Builder;
use MySqlMemory\Iterator\Combine\NestedLoopJoinIterator;
use MySqlMemory\Iterator\Combine\RecursiveUnionIterator;
use MySqlMemory\Iterator\Combine\SetOperationIterator;
use MySqlMemory\Iterator\Source\InlineIterator;
use MySqlMemory\Iterator\Source\SingleRowIterator;
use MySqlMemory\Iterator\Source\TableScanIterator;
use MySqlMemory\Iterator\Source\WorkingTableIterator;
use MySqlMemory\Iterator\Source\ZeroRowsIterator;
use MySqlMemory\Iterator\Transform\AggregateIterator;
use MySqlMemory\Iterator\Transform\DistinctIterator;
use MySqlMemory\Iterator\Transform\FilterIterator;
use MySqlMemory\Iterator\Transform\LimitIterator;
use MySqlMemory\Iterator\Transform\MaterializeIterator;
use MySqlMemory\Iterator\Transform\ProjectIterator;
use MySqlMemory\Iterator\Transform\SortIterator;
use MySqlMemory\Iterator\Transform\WindowIterator;
use MySqlMemory\Plan\Path\Combine\NestedLoopJoin;
use MySqlMemory\Plan\Path\Combine\RecursiveUnion;
use MySqlMemory\Plan\Path\Combine\SetOperation;
use MySqlMemory\Plan\Path\JoinKind;
use MySqlMemory\Plan\Path\SetKind;
use MySqlMemory\Plan\Path\Source\Inline;
use MySqlMemory\Plan\Path\Source\SingleRow;
use MySqlMemory\Plan\Path\Source\TableScan;
use MySqlMemory\Plan\Path\Source\WorkingTable;
use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Plan\Path\Transform\Aggregate;
use MySqlMemory\Plan\Path\Transform\Distinct;
use MySqlMemory\Plan\Path\Transform\Filter;
use MySqlMemory\Plan\Path\Transform\Limit;
use MySqlMemory\Plan\Path\Transform\Materialize;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Plan\Path\Transform\Sort;
use MySqlMemory\Plan\Path\Transform\Window;
use MySqlMemory\Plan\QueryPlan;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;

#[CoversClass(Builder::class)]
#[Small]
final class BuilderTest extends TestCase
{
    public function testBuildCreatesTheIteratorOfEachSourcePath(): void
    {
        $builder = new Builder();
        $working = new WorkingTable(1);

        self::assertInstanceOf(SingleRowIterator::class, $builder->build(new SingleRow()));
        self::assertInstanceOf(ZeroRowsIterator::class, $builder->build(new ZeroRows(1)));
        self::assertInstanceOf(InlineIterator::class, $builder->build(new Inline([], 0)));
        self::assertInstanceOf(WorkingTableIterator::class, $builder->build($working));
    }

    public function testBuildCreatesTheIteratorOfEachTransformPathOverTheIteratorOfItsInput(): void
    {
        $builder = new Builder();
        $input = new ZeroRows(1);
        $filter = $builder->build(new Filter($input, new ColumnRead(Domain::integer(), 0)));
        $aggregate = $builder->build(new Aggregate($input, [], [new Accumulation(AggregateFunction::Count, [], false, Domain::integer())]));
        $project = $builder->build(new Project($input, []));
        $sort = $builder->build(new Sort($input, []));
        $limit = $builder->build(new Limit($input, 1));
        $distinct = $builder->build(new Distinct($input, []));
        $materialize = $builder->build(new Materialize(new QueryPlan($input, [], [])));

        self::assertInstanceOf(FilterIterator::class, $filter);
        self::assertInstanceOf(ZeroRowsIterator::class, $filter->input);
        self::assertInstanceOf(AggregateIterator::class, $aggregate);
        self::assertInstanceOf(ZeroRowsIterator::class, $aggregate->input);
        self::assertInstanceOf(ProjectIterator::class, $project);
        self::assertInstanceOf(ZeroRowsIterator::class, $project->input);
        self::assertInstanceOf(SortIterator::class, $sort);
        self::assertInstanceOf(ZeroRowsIterator::class, $sort->input);
        self::assertInstanceOf(LimitIterator::class, $limit);
        self::assertInstanceOf(ZeroRowsIterator::class, $limit->input);
        self::assertInstanceOf(DistinctIterator::class, $distinct);
        self::assertInstanceOf(ZeroRowsIterator::class, $distinct->input);
        self::assertInstanceOf(MaterializeIterator::class, $materialize);
        self::assertInstanceOf(ZeroRowsIterator::class, $materialize->query);
    }

    public function testBuildCreatesTheIteratorOfEachCombiningPathOverTheIteratorsOfItsInputs(): void
    {
        $builder = new Builder();
        $working = new WorkingTable(1);
        $join = $builder->build(new NestedLoopJoin(new SingleRow(), new ZeroRows(1), JoinKind::Inner, null));
        $set = $builder->build(new SetOperation(SetKind::Union, true, new SingleRow(), new ZeroRows(0), []));
        $recursive = $builder->build(new RecursiveUnion(new SingleRow(), $working, $working, false, [], 10));

        self::assertInstanceOf(NestedLoopJoinIterator::class, $join);
        self::assertInstanceOf(SingleRowIterator::class, $join->left);
        self::assertInstanceOf(ZeroRowsIterator::class, $join->right);
        self::assertInstanceOf(SetOperationIterator::class, $set);
        self::assertInstanceOf(SingleRowIterator::class, $set->left);
        self::assertInstanceOf(ZeroRowsIterator::class, $set->right);
        self::assertInstanceOf(RecursiveUnionIterator::class, $recursive);
        self::assertInstanceOf(SingleRowIterator::class, $recursive->anchor);
        self::assertInstanceOf(WorkingTableIterator::class, $recursive->recursive);
    }

    public function testBuildRecordsTheIteratorOfEachTableScanByItsPath(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $scan = new TableScan($table);
        $builder = new Builder();

        $iterator = $builder->build(new Filter($scan, new ColumnRead(Domain::integer(), 0)));

        self::assertInstanceOf(FilterIterator::class, $iterator);
        self::assertInstanceOf(TableScanIterator::class, $iterator->input);
        self::assertSame([spl_object_id($scan) => $iterator->input], $builder->scans);
    }

    public function testBuildCreatesATreeThatExecutesThePaths(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(1);
        $working->rows = [[3], [1], [2]];
        $iterator = (new Builder())->build(new Limit(new Sort($working, [[0, Domain::integer(), false]]), 2));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([[1], [2], null], [$iterator->read(), $iterator->read(), $iterator->read()]);
    }

    public function testBuildCreatesTheIteratorOfAWindowOverTheIteratorOfItsInput(): void
    {
        $window = (new Builder())->build(new Window(new ZeroRows(1), [], [], WindowFrame::default(false), []));

        self::assertInstanceOf(WindowIterator::class, $window);
        self::assertInstanceOf(ZeroRowsIterator::class, $window->input);
    }
    public function testBuildMakesALockingReadOfALock(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $instance->connect()->query('CREATE TABLE d.t (a INT)');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $scan = new TableScan($table);
        $builder = new Builder();
        $iterator = $builder->build(new \MySqlMemory\Plan\Path\Transform\Lock($scan, null, [[$scan, 0, \MySqlMemory\Concurrency\LockMode::Exclusive, null]]));
        $scanned = $builder->scans[spl_object_id($scan)] ?? null;

        self::assertInstanceOf(\MySqlMemory\Iterator\Transform\LockIterator::class, $iterator);
        self::assertInstanceOf(TableScanIterator::class, $scanned);
        self::assertSame(\MySqlMemory\Concurrency\LockMode::Exclusive, $scanned->locking);
    }
}
