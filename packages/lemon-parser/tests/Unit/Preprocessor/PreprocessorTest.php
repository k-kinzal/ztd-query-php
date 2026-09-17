<?php

declare(strict_types=1);

namespace Tests\Unit\Preprocessor;

use LemonParser\Ast\Location;
use LemonParser\Preprocessor\Condition;
use LemonParser\Preprocessor\Exclusion;
use LemonParser\Preprocessor\Preprocessor;
use LemonParser\SyntaxException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Preprocessor::class)]
#[UsesClass(Condition::class)]
#[UsesClass(Exclusion::class)]
#[UsesClass(Location::class)]
#[UsesClass(SyntaxException::class)]
#[Small]
final class PreprocessorTest extends TestCase
{
    public function testPreprocess(): void
    {
        $preprocessor = new Preprocessor();
        $source = "a ::= B.\n%ifdef X\nb ::= C.\n%else\nb ::= D.\n%endif\ne ::= F.\n";

        self::assertSame("a ::= B.\n        \n        \n     \nb ::= D.\n      \ne ::= F.\n", $preprocessor->preprocess($source, []));
        self::assertSame("a ::= B.\n        \nb ::= C.\n     \n        \n      \ne ::= F.\n", $preprocessor->preprocess($source, ['X']));
    }

    public function testPreprocessNestsExcludedRegions(): void
    {
        $source = "%ifndef X\n%ifdef Y\na ::= B.\n%else\na ::= C.\n%endif\n%endif\nd ::= E.\n";

        self::assertSame("         \n        \n        \n     \n        \n      \n      \nd ::= E.\n", (new Preprocessor())->preprocess($source, ['X']));
        self::assertSame("         \n        \n        \n     \na ::= C.\n      \n      \nd ::= E.\n", (new Preprocessor())->preprocess($source, []));
    }

    public function testPreprocessCountsDirectivesOnlyAtTheStartOfALine(): void
    {
        $source = "a ::= B. %ifdef X\n %endif\n%if X\nc ::= D.\n%endif\n";

        self::assertSame("a ::= B. %ifdef X\n %endif\n     \n        \n      \n", (new Preprocessor())->preprocess($source, []));
    }

    public function testPreprocessSettlesADirectiveOnTheFirstLineOfAFileWithoutAFinalNewline(): void
    {
        self::assertSame("        \n  \n       ", (new Preprocessor())->preprocess("%ifdef X\na.\n%endif ", []));
    }

    public function testPreprocessRejectsABadExpression(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('%if syntax error:  A B <-- syntax error here at 3:1');

        (new Preprocessor())->preprocess("a.\nb.\n%if A B\nc.\n%endif\n", []);
    }

    public function testPreprocessRejectsAnUnterminatedRegion(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('unterminated %ifdef starting on line 2 at 2:1');

        (new Preprocessor())->preprocess("a ::= B.\n%ifdef X\nb ::= C.\n", []);
    }

    public function testDirective(): void
    {
        $preprocessor = new Preprocessor();

        self::assertSame('endif', $preprocessor->directive("%endif\n", 0));
        self::assertSame('else', $preprocessor->directive("x\n%else \n", 2));
        self::assertSame('ifdef', $preprocessor->directive('%ifdef X', 0));
        self::assertSame('if', $preprocessor->directive('%if X', 0));
        self::assertSame('ifndef', $preprocessor->directive('%ifndef X', 0));
        self::assertNull($preprocessor->directive('%endif', 0));
        self::assertNull($preprocessor->directive('%endifx', 0));
        self::assertNull($preprocessor->directive("%if\tX", 0));
        self::assertNull($preprocessor->directive('%left A.', 0));
    }

    public function testOpens(): void
    {
        $preprocessor = new Preprocessor();
        $condition = new Condition(['X']);

        self::assertFalse($preprocessor->opens($condition, "%ifdef X\n", 0, 'ifdef', new Location(1, 1)));
        self::assertTrue($preprocessor->opens($condition, "%ifdef Y\n", 0, 'ifdef', new Location(1, 1)));
        self::assertTrue($preprocessor->opens($condition, "%ifndef X\n", 0, 'ifndef', new Location(1, 1)));
        self::assertFalse($preprocessor->opens($condition, '%if X || Y', 0, 'if', new Location(1, 1)));
    }

    public function testSettle(): void
    {
        $preprocessor = new Preprocessor();
        $condition = new Condition([]);
        $exclusion = new Exclusion();
        $source = "%ifdef X\na.\n%else\nb.\n%endif\n";

        $afterIf = $preprocessor->settle($source, 0, 'ifdef', $exclusion, $condition, new Location(1, 1));
        $depthAfterIf = $exclusion->depth();
        $afterElse = $preprocessor->settle($afterIf, 12, 'else', $exclusion, $condition, new Location(3, 1));
        $depthAfterElse = $exclusion->depth();
        $afterEndif = $preprocessor->settle($afterElse, 21, 'endif', $exclusion, $condition, new Location(5, 1));

        self::assertSame([$source, 1], [$afterIf, $depthAfterIf]);
        self::assertSame(["        \n  \n%else\nb.\n%endif\n", 0], [$afterElse, $depthAfterElse]);
        self::assertSame([$afterElse, 0], [$afterEndif, $exclusion->depth()]);
    }

    public function testSettleNestsInsideAnExcludedRegion(): void
    {
        $preprocessor = new Preprocessor();
        $condition = new Condition([]);
        $exclusion = new Exclusion();
        $exclusion->enter(0, 1);

        $preprocessor->settle("%ifdef X\n%ifdef Y\n%else\n%endif\n", 9, 'ifdef', $exclusion, $condition, new Location(2, 1));
        $preprocessor->settle("%ifdef X\n%ifdef Y\n%else\n%endif\n", 18, 'else', $exclusion, $condition, new Location(3, 1));
        $blanked = $preprocessor->settle("%ifdef X\n%ifdef Y\n%else\n%endif\n", 24, 'endif', $exclusion, $condition, new Location(4, 1));

        self::assertSame(1, $exclusion->depth());
        self::assertSame("%ifdef X\n%ifdef Y\n%else\n%endif\n", $blanked);
    }

    public function testLineEnd(): void
    {
        $preprocessor = new Preprocessor();

        self::assertSame(2, $preprocessor->lineEnd("ab\ncd", 0));
        self::assertSame(5, $preprocessor->lineEnd("ab\ncd", 3));
    }

    public function testBlank(): void
    {
        self::assertSame("ab   \n  \n", (new Preprocessor())->blank("abcde\nfg\n", 2, 8));
    }
}
