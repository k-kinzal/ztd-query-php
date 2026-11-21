<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Rendering\Lexical;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\ValueRow;

#[CoversClass(ValueRow::class)]
#[Medium]
final class ValueRowTest extends TestCase
{
    public function testRenderWritesTheRowConstructor(): void
    {
        $semantics = new Semantics();
        $row = new ValueRow([new NumberLiteral('1'), new NullLiteral()]);
        $out = new Output(new Codec($semantics->profile()->grammar));
        $row->render($out);

        self::assertSame('ROW(1, NULL)', (new Lexical())->join($out->pieces()));
        self::assertCount(2, $row->values);
    }
}
