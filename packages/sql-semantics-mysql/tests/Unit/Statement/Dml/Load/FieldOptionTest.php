<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Load;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Rendering\Lexical;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOption;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOptionKind;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

#[CoversClass(FieldOption::class)]
#[Medium]
final class FieldOptionTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        $out = new Output(new Codec((new Semantics())->profile()->grammar));
        (new FieldOption(FieldOptionKind::OptionallyEnclosed, new Text('"')))->render($out);

        self::assertSame('OPTIONALLY ENCLOSED BY \'"\'', (new Lexical())->join($out->pieces()));
    }
}
