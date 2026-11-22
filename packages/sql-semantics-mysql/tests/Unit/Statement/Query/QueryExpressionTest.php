<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(QueryExpression::class)]
#[Medium]
final class QueryExpressionTest extends TestCase
{
    public function testDeriveStatementRecordsTheRowsOfTheBody(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->inspect('SELECT a FROM t UNION SELECT c FROM u ORDER BY a LIMIT 1', [$t, $u]);

        self::assertInstanceOf(QueryExpression::class, $operation->statement);
        self::assertInstanceOf(SetOperation::class, $operation->statement->body);
        self::assertSame($operation->facts->query($operation->statement->body), $operation->facts->output);
    }

    public function testDeriveQueryResolvesOrdinalsAgainstTheOutput(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 UNION SELECT 2 ORDER BY 1');
        $ordinal = $operation->statement instanceof QueryExpression ? $operation->statement->orderBy[0]->expression : null;

        self::assertInstanceOf(OutputOrdinal::class, $ordinal);
        self::assertInstanceOf(AliasTarget::class, $operation->facts->scalar($ordinal)->resolution);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveQueryBindsTheCommonTablesForTheBody(): void
    {
        $semantics = new Inspection();
        $operation = $semantics->inspect('WITH c (x) AS (SELECT 1) SELECT x FROM c', []);

        self::assertInstanceOf(QueryExpression::class, $operation->statement);
        self::assertNotNull($operation->statement->with);
        self::assertInstanceOf(Known::class, $operation->field('x')->type);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesTheWithClauseTheBodyTheOrderingAndTheLimit(): void
    {
        $semantics = new Inspection();

        self::assertSame('WITH c AS (SELECT 1) SELECT * FROM c ORDER BY 1 LIMIT 2', $semantics->inspect('with c as (select 1) select * from c order by 1 limit 2')->toString());
        self::assertSame('(SELECT 1) ORDER BY 1', $semantics->inspect('(select 1) order by 1')->toString());
    }

    public function testAWrapperWithoutClausesIsRejected(): void
    {
        $this->expectExceptionMessage('A query expression holds a WITH clause, an ordering or a limit.');

        new QueryExpression(null, new ParenthesizedQuery(new Select([], [new SelectExpression(new NumberLiteral('1'))])));
    }

    public function testAnOrderingOverASingleBlockIsRejected(): void
    {
        $this->expectExceptionMessage('The ordering and the limit of a single query block belong to the block unless an ORDER BY follows a block that orders or limits its rows.');

        new QueryExpression(null, new Select([], [new SelectExpression(new NumberLiteral('1'))]), [], new RowLimit(new NumberLiteral('1')));
    }

    public function testAnIntegerOrderingItemIsRejected(): void
    {
        $this->expectExceptionMessage('An integer in ORDER BY or GROUP BY is a select list position.');

        new QueryExpression(null, new ParenthesizedQuery(new Select([], [new SelectExpression(new NumberLiteral('1'))])), [new OrderItem(new NumberLiteral('1'))]);
    }
}
