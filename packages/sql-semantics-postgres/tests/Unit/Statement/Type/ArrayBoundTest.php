<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\ArrayBound;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(ArrayBound::class)]
#[Small]
final class ArrayBoundTest extends TestCase
{
    public function testRenderWritesTheBracketsAndTheSize(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new ArrayBound(new IntegerConstant('3')))->render($out);
        self::assertSame('[3]', (new Lexical())->join($out->pieces()));
        $second = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new ArrayBound())->render($second);
        self::assertSame('[]', (new Lexical())->join($second->pieces()));
    }
}
