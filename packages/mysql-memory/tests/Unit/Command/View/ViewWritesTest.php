<?php

declare(strict_types=1);

namespace Tests\Unit\Command\View;

use MySqlMemory\Command\View\ViewWrites;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;

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
        self::assertSame(['`d`.`t`', [['a', '`a`', '`t`.`a`'], ['bb', null, '(b + 1)']], 'b > 0', true], [$writes->table, $writes->columns, $writes->where, $writes->keyed]);
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
        $writes = new ViewWrites('`d`.`t`', '`t`', '', [['x', '`a`', '`t`.`a`']], null);

        self::assertSame([['x', '`a`', '`t`.`a`'], null], [$writes->column('X'), $writes->column('y')]);
    }

    public function testWrittenRefusesAComputedColumn(): void
    {
        $writes = new ViewWrites('`d`.`t`', '`t`', '', [['bb', null, '(b + 1)']], null);

        $this->expectExceptionCode(1348);

        $writes->written('bb');
    }

    public function testReadsReplacesTheColumnsOfTheView(): void
    {
        $session = (new Instance())->connect();
        $writes = new ViewWrites('`d`.`t`', '`t`', '', [['x', '`a`', '`t`.`a`']], null);
        $text = 'SELECT x, v.x, o.x, y';

        $edits = $writes->reads($session->semantics()->parser()->parse($text), $text, ['v'], []);

        self::assertSame('SELECT `t`.`a`, `t`.`a`, o.x, y', ViewWrites::apply($text, $edits));
    }

    public function testApplyReplacesFromTheLast(): void
    {
        self::assertSame('aXcY', ViewWrites::apply('abcd', [1 => [1, 2, 'X'], 3 => [3, 4, 'Y']]));
    }

    public function testUnquotedRemovesTheBackticks(): void
    {
        self::assertSame(['a`b', 'c'], [ViewWrites::unquoted('`a``b`'), ViewWrites::unquoted('c')]);
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

        self::assertSame([['x', '`b`', '`q`.`b`'], ['y', null, '(a * 2)']], ViewWrites::columns($view, $block, $block->from, '`q`', $session->semantics()->parser()->parse($view->select)));
    }

    public function testKeyedTellsWhetherTheColumnsWriteAUniqueKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT, UNIQUE KEY (a, b))');
        $base = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($base);

        self::assertSame([true, false], [ViewWrites::keyed($base, [['x', '`A`', '`t`.`a`'], ['b', '`b`', '`t`.`b`']]), ViewWrites::keyed($base, [['a', '`a`', '`t`.`a`'], ['b', null, '(b + 1)']])]);
    }

    public function testKeyedNeedsEveryColumnOfATableWithoutAUniqueKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT, KEY (a))');
        $base = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($base);

        self::assertSame([true, false], [ViewWrites::keyed($base, [['a', '`a`', '`t`.`a`'], ['b', '`b`', '`t`.`b`']]), ViewWrites::keyed($base, [['a', '`a`', '`t`.`a`']])]);
    }
}
