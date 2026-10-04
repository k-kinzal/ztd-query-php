<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Load;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOption;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOptionKind;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(FieldOption::class)]
#[Medium]
final class FieldOptionTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        $out = new Output(new Codec((new Semantics(Dialect::MySql))->profile()->grammar));
        (new FieldOption(FieldOptionKind::OptionallyEnclosed, new Text('"')))->render($out);

        self::assertSame('OPTIONALLY ENCLOSED BY \'"\'', (new Lexical())->join($out->pieces()));
    }
}
