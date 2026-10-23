<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(CommonTableExpression::class)]
#[Medium]
final class CommonTableExpressionTest extends TestCase
{
    public function testRenderWritesTheNameTheColumnsAndTheQuery(): void
    {
        self::assertSame('WITH c (x, y) AS (SELECT 1, 2) SELECT x FROM c', (new Semantics(Dialect::MySql))->analyze('with c(x,y) as (select 1, 2) select x from c')->toString());
        self::assertSame('WITH c AS ((SELECT 1)) SELECT 1', (new Semantics(Dialect::MySql))->analyze('with c as ((select 1)) select 1')->toString());
    }

    public function testACatalogDependentColumnListOfAnotherLengthIsReported(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE d.t(a INT)')]);
        $operation = $semantics->analyze('WITH c (x, y) AS (SELECT * FROM d.t) SELECT x FROM c', $context);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(CountMismatch::class, $operation->facts->diagnostics[0]);
        self::assertInstanceOf(Known::class, $operation->field('x')->type);
        self::assertInstanceOf(Invalid::class, $semantics->analyze('WITH c (x, y) AS (SELECT * FROM d.t) SELECT y FROM c', $context)->field('y')->type);
    }
}
