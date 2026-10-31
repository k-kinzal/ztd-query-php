<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\ExpressionFacts;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\MissingColumn;

#[CoversClass(ExpressionFacts::class)]
#[Medium]
final class ExpressionFactsTest extends TestCase
{
    public function testDeriveResolvesTheOrderingAgainstTheOutputAndBindsTheTables(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('WITH c AS (SELECT 1 AS x) (SELECT x FROM c) ORDER BY 1 LIMIT 1', []);

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        self::assertInstanceOf(QueryExpression::class, $operation->query);
        self::assertSame([], $operation->diagnostics());
        self::assertInstanceOf(AliasTarget::class, $operation->query->orderBy[0]->expression->scalarInformation()->resolution);
        self::assertSame($operation->query->body->queryInformation(), $operation->output());
    }

    public function testOrderingSeesTheOutputColumnsButNoTableOfTheQuery(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.6.51');
        $table = $semantics->analyze('CREATE TABLE t (a INT NOT NULL)');
        $named = $semantics->analyze('SELECT (SELECT a AS x FROM t UNION SELECT 2 FROM DUAL LIMIT 1 ORDER BY x)', [$table]);
        $qualified = $semantics->analyze('SELECT (SELECT a AS x FROM t UNION SELECT 2 FROM DUAL LIMIT 1 ORDER BY t.a)', [$table]);

        self::assertSame([], $named->diagnostics());
        self::assertCount(1, $qualified->diagnostics());
        self::assertInstanceOf(MissingColumn::class, $qualified->diagnostics()[0]);
        self::assertSame('a', $qualified->diagnostics()[0]->name->value);
    }
}
