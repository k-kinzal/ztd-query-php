<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\With\CommonTableExpression::class)]
#[Small]
final class CommonTableExpressionTest extends TestCase
{
    public function testRenderWritesTheNameColumnsAndStatement(): void
    {
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Statement\Query\With\CommonTableExpression(new \SqlSemantics\Statement\Identifier\Name('x'), new \SqlSemantics\Platform\PostgreSql\Statement\Query\Select([new \SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1')))]), [new \SqlSemantics\Statement\Identifier\Name('a')], \SqlSemantics\Platform\PostgreSql\Statement\Query\With\Materialization::NotMaterialized))->render($out);
        self::assertSame('x (a) AS NOT MATERIALIZED (SELECT 1)', (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
    }
}
