<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\Negation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\PatternMatch;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\PatternOperator;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(PatternMatch::class)]
#[Small]
final class PatternMatchTest extends TestCase
{
    public function testDeriveScalarIsBooleanForStrings(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new PatternMatch(new Constant(new StringConstant('a')), PatternOperator::SimilarTo, false, new Constant(new StringConstant('a'))), $derivation->environment());
        self::assertEquals(new Known(Builtin::Bool), $fact->type);
    }

    public function testRenderWritesTheEscape(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new PatternMatch(new NullLiteral(), PatternOperator::ILike, true, new NullLiteral(), new NullLiteral()))->render($out);
        self::assertSame('NULL NOT ILIKE NULL ESCAPE NULL', (new Lexical())->join($out->pieces()));
    }

    public function testAcceptsANegatedPatternBeforeTheEscape(): void
    {
        $pattern = new Negation(new NullLiteral());
        self::assertSame($pattern, (new PatternMatch(new NullLiteral(), PatternOperator::Like, false, $pattern, new NullLiteral()))->right);
    }

    public function testRejectsAPatternThatWouldTakeTheEscape(): void
    {
        $inner = new Negation(new PatternMatch(new NullLiteral(), PatternOperator::Like, false, new NullLiteral()));
        $this->expectExceptionMessage('The pattern or the escape needs parentheses to keep its place.');
        new PatternMatch(new NullLiteral(), PatternOperator::Like, false, $inner, new NullLiteral());
    }
}
