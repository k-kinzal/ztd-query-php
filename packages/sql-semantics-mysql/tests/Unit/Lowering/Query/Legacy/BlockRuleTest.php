<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Legacy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\Legacy\BlockRule;
use SqlSemantics\Platform\MySql\Platform;

#[CoversClass(BlockRule::class)]
#[Medium]
final class BlockRuleTest extends TestCase
{
    public function testPartLowersEveryForm(): void
    {
        self::assertSame('SELECT 1 ORDER BY 1 LIMIT 1 FOR UPDATE', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select 1 order by 1 limit 1 for update')->toString());
        self::assertSame('SELECT 1 INTO @x LOCK IN SHARE MODE', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select 1 into @x lock in share mode')->toString());
        self::assertSame('SELECT 1 FROM t FOR UPDATE', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select 1 from t for update')->toString());
    }

    public function testFullLowersTheBlockWithAFromClause(): void
    {
        self::assertSame('SELECT a INTO @x FROM t WHERE 1 GROUP BY a HAVING 1 ORDER BY a LIMIT 1 FOR UPDATE', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select a into @x from t where 1 group by a having 1 order by a limit 1 for update')->toString());
        self::assertSame('SELECT a FROM t PROCEDURE ANALYSE() FOR UPDATE', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select a from t procedure analyse() for update')->toString());
    }

    public function testHeadLowersTheOptionsAndItems(): void
    {
        self::assertSame('SELECT DISTINCT a FROM t', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select distinct a from t')->toString());
    }

    public function testIntoLowersEveryClauseLayoutOf56(): void
    {
        self::assertSame('SELECT 1 ORDER BY 1 LIMIT 1', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select 1 order by 1 limit 1')->toString());
        self::assertSame('SELECT 1 INTO @x', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select 1 into @x')->toString());
        self::assertSame('SELECT 1 INTO @x FROM t', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select 1 into @x from t')->toString());
        self::assertSame('SELECT 1 FROM t INTO @x LOCK IN SHARE MODE', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select 1 from t into @x lock in share mode')->toString());
    }

    public function testFromLowersTheFromClauseOf56(): void
    {
        self::assertSame('SELECT 1 FROM DUAL WHERE 1 LIMIT 1', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select 1 from dual where 1 limit 1')->toString());
        self::assertSame('SELECT a FROM t WHERE 1 GROUP BY a HAVING 1 ORDER BY a LIMIT 1 PROCEDURE ANALYSE(1, 2)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select a from t where 1 group by a having 1 order by a limit 1 procedure analyse(1, 2)')->toString());
    }

    public function testExpressionLowersTheTableExpressionOf57(): void
    {
        self::assertSame('SELECT 1 FROM (SELECT a FROM t WHERE 1 GROUP BY a HAVING 1 ORDER BY a LIMIT 1 FOR UPDATE) AS d', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select 1 from (select a from t where 1 group by a having 1 order by a limit 1 for update) as d')->toString());
    }

    public function testOptionalLowersTheOptionalFromClauseOf56(): void
    {
        self::assertSame('SELECT 1 FROM (SELECT 1 LIMIT 1) AS d, (SELECT 2 FROM t FOR UPDATE) AS e', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select 1 from (select 1 limit 1) as d, (select 2 from t for update) as e')->toString());
    }

    public function testCreateLowersTheQueryOfCreateTableSelect(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $tree = $platform->parser($profile)->parse('CREATE TABLE t SELECT a FROM u LIMIT 1');
        $verb = $tree->children[0];
        self::assertInstanceOf(Node::class, $verb);
        $statement = $verb->children[0];
        self::assertInstanceOf(Node::class, $statement);
        $create = $statement->children[0];
        self::assertInstanceOf(Node::class, $create);
        $second = $create->children[5];
        self::assertInstanceOf(Node::class, $second);
        $third = $second->children[2];
        self::assertInstanceOf(Node::class, $third);
        $select = $third->children[2];
        self::assertInstanceOf(Node::class, $select);
        $block = (new BlockRule($lowering))->create($select);

        self::assertNotNull($block->from);
        self::assertNotNull($block->trailer->limit);
    }

    public function testDerivedLowersTheBlocksOfSubqueries(): void
    {
        self::assertSame('SELECT (SELECT a FROM t LIMIT 1 FOR UPDATE) AS v FROM u', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select (select a from t limit 1 for update) as v from u')->toString());
        self::assertSame('SELECT (SELECT DISTINCT a FROM t) AS v FROM u', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select (select distinct a from t) as v from u')->toString());
    }

    public function testDerivedRejectsProcedureAnalyseInASubquery(): void
    {
        $this->expectExceptionMessage('Incorrect usage of PROCEDURE and subquery: PROCEDURE ANALYSE belongs to the outermost query block.');

        (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT (SELECT a FROM t PROCEDURE ANALYSE())');
    }

    public function testFullRejectsProcedureAnalyseWithInto(): void
    {
        $this->expectExceptionMessage('Incorrect usage of PROCEDURE and INTO: a query block with PROCEDURE ANALYSE has no INTO.');

        (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT a FROM t PROCEDURE ANALYSE() INTO @x');
    }

    public function testIntoRejectsProcedureAnalyseAfterAnIntoOf56(): void
    {
        self::assertSame('SELECT a FROM t PROCEDURE ANALYSE() INTO @x', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT a FROM t PROCEDURE ANALYSE() INTO @x')->toString());

        $this->expectExceptionMessage('Incorrect usage of PROCEDURE and INTO: PROCEDURE ANALYSE follows no INTO.');

        (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT a INTO @x FROM t PROCEDURE ANALYSE()');
    }
}
