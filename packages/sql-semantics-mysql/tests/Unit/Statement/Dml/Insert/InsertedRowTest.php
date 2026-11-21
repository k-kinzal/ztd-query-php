<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Rendering\Lexical;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertedRow;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;

#[CoversClass(InsertedRow::class)]
#[Medium]
final class InsertedRowTest extends TestCase
{
    public function testRenderWritesTheParenthesizedValues(): void
    {
        $out = new Output(new Codec((new Semantics())->profile()->grammar));
        (new InsertedRow([new NumberLiteral('1'), new DefaultRequest()]))->render($out);

        self::assertSame('(1, DEFAULT)', (new Lexical())->join($out->pieces()));
    }
}
