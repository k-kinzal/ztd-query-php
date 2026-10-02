<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBound;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBoundKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameMode;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\WindowFrame;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\WindowSpecification;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortDirection;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortItem;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(WindowSpecification::class)]
#[Small]
final class WindowSpecificationTest extends TestCase
{
    public function testDeriveClauseDerivesPartitionOrderAndFrame(): void
    {
        $partition = new Constant(new IntegerConstant('1'));
        $key = new Constant(new IntegerConstant('2'));
        $offset = new Constant(new IntegerConstant('3'));
        $window = new WindowSpecification(null, [$partition], [new SortItem($key)], new WindowFrame(FrameMode::Rows, new FrameBound(FrameBoundKind::OffsetPreceding, $offset)));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $window->deriveClause($derivation, $derivation->environment());
        self::assertSame([true, true, true], [$derivation->facts()->covers($partition), $derivation->facts()->covers($key), $derivation->facts()->covers($offset)]);
    }

    public function testRenderWritesTheSpecificationInParentheses(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new WindowSpecification(new Name('w'), [new Constant(new IntegerConstant('1'))], [new SortItem(new Constant(new IntegerConstant('2')), SortDirection::Descending)], new WindowFrame(FrameMode::Rows, new FrameBound(FrameBoundKind::UnboundedPreceding))))->render($out);
        self::assertSame('(w PARTITION BY 1 ORDER BY 2 DESC ROWS UNBOUNDED PRECEDING)', (new Lexical())->join($out->pieces()));
        $second = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new WindowSpecification())->render($second);
        self::assertSame('()', (new Lexical())->join($second->pieces()));
    }
}
