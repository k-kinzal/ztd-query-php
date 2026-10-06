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
use SqlSemantics\Platform\PostgreSql\Statement\Type\ArraySpecifier;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(ArraySpecifier::class)]
#[Small]
final class ArraySpecifierTest extends TestCase
{
    public function testRenderWritesEveryDimension(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new ArraySpecifier([new ArrayBound(new IntegerConstant('3')), new ArrayBound()]))->render($out);
        self::assertSame('[3] []', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheKeywordForm(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new ArraySpecifier([new ArrayBound(new IntegerConstant('3'))], true))->render($out);
        self::assertSame('ARRAY [3]', (new Lexical())->join($out->pieces()));
        $second = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new ArraySpecifier([], true))->render($second);
        self::assertSame('ARRAY', (new Lexical())->join($second->pieces()));
    }

    public function testRejectsTwoDimensionsAfterTheKeyword(): void
    {
        $this->expectExceptionMessage('The ARRAY keyword takes at most one sized dimension');
        new ArraySpecifier([new ArrayBound(new IntegerConstant('1')), new ArrayBound(new IntegerConstant('2'))], true);
    }
}
