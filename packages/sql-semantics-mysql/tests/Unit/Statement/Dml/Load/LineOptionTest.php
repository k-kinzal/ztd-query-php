<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Load;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LineOption;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LineOptionKind;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(LineOption::class)]
#[Medium]
final class LineOptionTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        $out = new Output(new Codec((new Semantics(Dialect::MySql))->profile()->grammar));
        (new LineOption(LineOptionKind::Starting, new Text('x')))->render($out);

        self::assertSame("STARTING BY 'x'", (new Lexical())->join($out->pieces()));
    }
}
