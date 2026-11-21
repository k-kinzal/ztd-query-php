<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\GroupingFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

#[CoversClass(GroupingFunction::class)]
#[Small]
final class GroupingFunctionTest extends TestCase
{
    public function testOutputNameIsGrouping(): void
    {
        self::assertSame('grouping', (new GroupingFunction([new NullLiteral()]))->outputName()->value);
    }

    public function testDeriveScalarIsANonNullInteger(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new GroupingFunction([new NullLiteral()]), $derivation->environment());
        self::assertEquals(new Known(Builtin::Int4), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testRenderWritesTheArguments(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new GroupingFunction([new Constant(new IntegerConstant('1')), new NullLiteral()]))->render($out);
        self::assertSame('GROUPING (1, NULL)', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsNoArgument(): void
    {
        $this->expectExceptionMessage('GROUPING takes at least one expression.');
        new GroupingFunction([]);
    }
}
