<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Invalid;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CommonTableExpression::class)]
#[Medium]
final class CommonTableExpressionTest extends TestCase
{
    public function testRenderWritesTheNameTheColumnsAndTheQuery(): void
    {
        self::assertSame('WITH c (x, y) AS (SELECT 1, 2) SELECT x FROM c', (new Inspection())->diagnose('with c(x,y) as (select 1, 2) select x from c')->toString());
        self::assertSame('WITH c AS ((SELECT 1)) SELECT 1', (new Inspection())->diagnose('with c as ((select 1)) select 1')->toString());
    }

    public function testAColumnListOfAnotherLengthIsReported(): void
    {
        $operation = (new Inspection())->diagnose('WITH c (x, y) AS (SELECT 1) SELECT x FROM c', []);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(CountMismatch::class, $operation->facts->diagnostics[0]);
        self::assertInstanceOf(Known::class, $operation->field('x')->type);
        self::assertInstanceOf(Invalid::class, (new Inspection())->diagnose('WITH c (x, y) AS (SELECT 1) SELECT y FROM c', [])->field('y')->type);
    }
}
