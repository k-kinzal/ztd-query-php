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

#[CoversClass(ExpressionFacts::class)]
#[Medium]
final class ExpressionFactsTest extends TestCase
{
    public function testDeriveResolvesTheOrderingAgainstTheOutputAndBindsTheTables(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('WITH c AS (SELECT 1 AS x) (SELECT x FROM c) ORDER BY 1 LIMIT 1', []);

        self::assertInstanceOf(QueryExpression::class, $operation->statement);
        self::assertSame([], $operation->facts->diagnostics);
        self::assertInstanceOf(AliasTarget::class, $operation->facts->scalar($operation->statement->orderBy[0]->expression)->resolution);
        self::assertSame($operation->facts->query($operation->statement->body), $operation->facts->output);
    }
}
