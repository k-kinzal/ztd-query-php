<?php

declare(strict_types=1);

namespace Tests\Unit\Command\View;

use MySqlMemory\Command\View\ViewWrites;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(ViewWrites::class)]
#[Small]
final class ViewWritesTest extends TestCase
{
    public function testOfAnswersTheColumnsAndTheConditionOfAnUpdatableView(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT PRIMARY KEY, b INT)');
        $session->query('CREATE VIEW w AS SELECT a, b + 1 AS bb FROM t WHERE b > 0');
        $view = $session->instance->dictionary->schema('d')?->views['w'];
        self::assertNotNull($view);

        $writes = ViewWrites::of($view, $session);

        self::assertNotNull($writes);
        self::assertSame(['d', 't', true], [$writes->table->schema?->value, $writes->table->name->value, $writes->keyed]);
        self::assertInstanceOf(Select::class, $view->query);
        self::assertSame($view->query->where, $writes->where);
        self::assertSame(['a', null], [$writes->columns[0][1]?->value, $writes->columns[1][1]]);
    }

    public function testOfAnswersNullForAViewThatIsNotMerged(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE VIEW l AS SELECT a FROM t LIMIT 1');
        $view = $session->instance->dictionary->schema('d')?->views['l'];
        self::assertNotNull($view);

        self::assertNull(ViewWrites::of($view, $session));
    }

    public function testColumnFindsAColumnWithoutCase(): void
    {
        $writes = new ViewWrites(new QualifiedName(new Name('t'), new Name('d')), new Name('t'), null, [['x', new Name('a'), new ColumnUse(new Name('a'), new QualifiedName(new Name('t')))]], null);

        self::assertEquals([['x', new Name('a'), new ColumnUse(new Name('a'), new QualifiedName(new Name('t')))], null], [$writes->column('X'), $writes->column('y')]);
    }

    public function testWrittenRefusesAComputedColumn(): void
    {
        $writes = new ViewWrites(new QualifiedName(new Name('t'), new Name('d')), new Name('t'), null, [['bb', null, new ColumnUse(new Name('b'))]], null);

        $this->expectExceptionCode(1348);

        $writes->written('bb');
    }

    public function testBlockAnswersTheQueryBlockOfAMergeableView(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE VIEW v AS SELECT a FROM t');
        $session->query('CREATE ALGORITHM=TEMPTABLE VIEW m AS SELECT a FROM t');
        $session->query('CREATE VIEW o AS SELECT 1 AS a');
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);

        self::assertSame([true, null, null], [ViewWrites::block($schema->views['v']) instanceof Select, ViewWrites::block($schema->views['m']), ViewWrites::block($schema->views['o'])]);
    }

    public function testColumnsAnswersTheBaseColumnOrTheExpressionOfEachColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('CREATE VIEW w (x, y) AS SELECT b, a * 2 FROM t AS q');
        $view = $session->instance->dictionary->schema('d')?->views['w'];
        self::assertNotNull($view);
        $block = ViewWrites::block($view);
        self::assertNotNull($block);
        self::assertInstanceOf(TableReference::class, $block->from);

        $columns = ViewWrites::columns(\MySqlMemory\Session\Statement\Catalog::snapshot($session)->view($view), $block, $block->from, new Name('q'));
        self::assertNotNull($columns);
        self::assertInstanceOf(ColumnUse::class, $columns[0][2]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression::class, $block->items[1]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Grouped::class, $columns[1][2]);
        self::assertSame(['x', 'b', 'q'], [$columns[0][0], $columns[0][1]?->value, $columns[0][2]->qualifier?->name->value]);
        self::assertSame($block->items[1]->expression, $columns[1][2]->operand);
    }

    public function testKeyedTellsWhetherTheColumnsWriteAUniqueKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT, UNIQUE KEY (a, b))');
        $base = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($base);

        self::assertSame([true, false], [ViewWrites::keyed($base, [['x', new Name('A'), new ColumnUse(new Name('a'), new QualifiedName(new Name('t')))], ['b', new Name('b'), new ColumnUse(new Name('b'), new QualifiedName(new Name('t')))]]), ViewWrites::keyed($base, [['a', new Name('a'), new ColumnUse(new Name('a'), new QualifiedName(new Name('t')))], ['b', null, new ColumnUse(new Name('b'))]])]);
    }

    public function testKeyedNeedsEveryColumnOfATableWithoutAUniqueKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT, KEY (a))');
        $base = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($base);

        self::assertSame([true, false], [ViewWrites::keyed($base, [['a', new Name('a'), new ColumnUse(new Name('a'), new QualifiedName(new Name('t')))], ['b', new Name('b'), new ColumnUse(new Name('b'), new QualifiedName(new Name('t')))]]), ViewWrites::keyed($base, [['a', new Name('a'), new ColumnUse(new Name('a'), new QualifiedName(new Name('t')))]])]);
    }
}
