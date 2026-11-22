<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Legacy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\Legacy\UnionRule;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\AnalysisException;
use SqlSemantics\Platform\MySql\Model\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(UnionRule::class)]
#[Medium]
final class UnionRuleTest extends TestCase
{
    public function testStatementLiftsTheOrderingOfTheLastSelect(): void
    {
        $operation = (new Inspection('mysql-5.7.44'))->inspect('SELECT a FROM t UNION SELECT b FROM u ORDER BY 1 LIMIT 2');

        self::assertInstanceOf(QueryExpression::class, $operation->statement);
        self::assertInstanceOf(SetOperation::class, $operation->statement->body);
        self::assertSame('SELECT a FROM t UNION SELECT b FROM u ORDER BY 1 LIMIT 2', $operation->toString());
    }

    public function testOperandLowersEveryOperandForm(): void
    {
        self::assertSame('SELECT 1 UNION (SELECT 2) UNION ALL SELECT 3', (new Inspection('mysql-5.6.51'))->inspect('select 1 union (select 2) union all select 3')->toString());
        self::assertSame('(SELECT 1) UNION DISTINCT SELECT 2', (new Inspection('mysql-5.7.44'))->inspect('(select 1) union distinct select 2')->toString());
    }

    public function testParenLowersNestedParentheses(): void
    {
        self::assertSame('((SELECT 1))', (new Inspection('mysql-5.6.51'))->inspect('((select 1))')->toString());
    }

    public function testChainRejectsAnOrderingBeforeTheLastSelect(): void
    {
        $this->expectException(AnalysisException::class);

        (new Inspection('mysql-5.7.44'))->inspect('SELECT 1 ORDER BY 1 UNION SELECT 2');
    }

    public function testOrderingLowersTheClausesAfterAParenthesizedOperand(): void
    {
        self::assertSame('(SELECT 1) UNION (SELECT 2) ORDER BY 1 LIMIT 1', (new Inspection('mysql-5.6.51'))->inspect('(select 1) union (select 2) order by 1 limit 1')->toString());
        self::assertSame('(SELECT 1) LIMIT 1', (new Inspection('mysql-5.7.44'))->inspect('(select 1) limit 1')->toString());
    }

    public function testViewQueryLowersTheQueryOfAView(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $tree = $platform->parser($profile)->parse('CREATE VIEW v AS SELECT a FROM t UNION (SELECT 2)');
        $statement = $tree->children[0];
        self::assertInstanceOf(Node::class, $statement);
        $create = $statement->children[0];
        self::assertInstanceOf(Node::class, $create);
        $definition = $create->children[0];
        self::assertInstanceOf(Node::class, $definition);
        $tail = $definition->children[1];
        self::assertInstanceOf(Node::class, $tail);
        $body = $tail->children[1];
        self::assertInstanceOf(Node::class, $body);
        $view = $body->children[0];
        self::assertInstanceOf(Node::class, $view);
        $select = $view->children[5];
        self::assertInstanceOf(Node::class, $select);
        $aux = $select->children[0];
        self::assertInstanceOf(Node::class, $aux);

        $query = (new UnionRule($lowering))->viewQuery($aux);

        self::assertInstanceOf(SetOperation::class, $query);
        self::assertInstanceOf(ParenthesizedQuery::class, $query->right);
    }

    public function testViewLowersTheBlockOfAView(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $tree = $platform->parser($profile)->parse('CREATE VIEW v AS SELECT a FROM t UNION (SELECT 2)');
        $statement = $tree->children[0];
        self::assertInstanceOf(Node::class, $statement);
        $create = $statement->children[0];
        self::assertInstanceOf(Node::class, $create);
        $definition = $create->children[0];
        self::assertInstanceOf(Node::class, $definition);
        $tail = $definition->children[1];
        self::assertInstanceOf(Node::class, $tail);
        $body = $tail->children[1];
        self::assertInstanceOf(Node::class, $body);
        $view = $body->children[0];
        self::assertInstanceOf(Node::class, $view);
        $select = $view->children[5];
        self::assertInstanceOf(Node::class, $select);
        $aux = $select->children[0];
        self::assertInstanceOf(Node::class, $aux);

        $first = $aux->children[0];
        self::assertInstanceOf(Node::class, $first);
        $block = (new UnionRule($lowering))->view($first);

        self::assertNotNull($block->from);
        self::assertCount(1, $block->items);
    }
}
