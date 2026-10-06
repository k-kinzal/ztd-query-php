<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\With\CycleClause::class)]
#[Small]
final class CycleClauseTest extends TestCase
{
    public function testRenderWritesTheMarkValues(): void
    {
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Statement\Query\With\CycleClause([new \SqlSemantics\Statement\Identifier\Name('a')], new \SqlSemantics\Statement\Identifier\Name('m'), new \SqlSemantics\Statement\Identifier\Name('p'), new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1')), new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()))->render($out);
        self::assertSame('CYCLE a SET m TO 1 DEFAULT NULL USING p', (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
    }
}
