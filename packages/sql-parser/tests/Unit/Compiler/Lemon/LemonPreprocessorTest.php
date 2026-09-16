<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler\Lemon;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Compiler\GrammarSourceException;
use SqlParser\Compiler\Lemon\LemonCondition;
use SqlParser\Compiler\Lemon\LemonPreprocessor;

#[CoversClass(LemonPreprocessor::class)]
#[UsesClass(GrammarSourceException::class)]
#[UsesClass(LemonCondition::class)]
#[Small]
final class LemonPreprocessorTest extends TestCase
{
    public function testProcessBlanksInactiveBranchesAndKeepsLineCount(): void
    {
        $source = "a\n%ifdef X\nb\n%else\nc\n%endif X\n%ifndef X\nd\n%if X || Y\ne\n%endif\n%endif\nf";
        $processed = (new LemonPreprocessor(new LemonCondition(['Y'])))->process($source);

        self::assertSame("a\n\n\n\nc\n\n\nd\n\ne\n\n\nf", $processed);
        self::assertSame(substr_count($source, "\n"), substr_count($processed, "\n"));
    }

    public function testProcessRejectsAnElseWithoutIf(): void
    {
        $this->expectException(GrammarSourceException::class);

        (new LemonPreprocessor())->process("%else\n");
    }

    public function testProcessRejectsASecondElse(): void
    {
        $this->expectException(GrammarSourceException::class);

        (new LemonPreprocessor())->process("%ifdef A\n%else\n%else\n%endif\n");
    }

    public function testProcessRejectsAnUnterminatedConditional(): void
    {
        $this->expectException(GrammarSourceException::class);

        (new LemonPreprocessor())->process("%ifdef A\nb\n");
    }

    public function testConditionOf(): void
    {
        $preprocessor = new LemonPreprocessor();

        self::assertSame('A || B', $preprocessor->conditionOf('if', ' A || B // note'));
        self::assertSame('A', $preprocessor->conditionOf('ifdef', ' A trailing'));
        self::assertSame('', $preprocessor->conditionOf('ifndef', ''));
    }
}
