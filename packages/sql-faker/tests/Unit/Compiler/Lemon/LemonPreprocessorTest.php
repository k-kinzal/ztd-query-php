<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Compiler\Lemon;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Compiler\Lemon\LemonCondition;
use SqlFaker\Compiler\Lemon\LemonPreprocessor;

#[CoversClass(LemonPreprocessor::class)]
#[UsesClass(LemonCondition::class)]
#[Small]
final class LemonPreprocessorTest extends TestCase
{
    public function testProcessSelectsNestedSourceBranchesAndPreservesLineCount(): void
    {
        $input = <<<'LEMON'
%ifdef EXTENSION
extension ::= ENABLED.
%ifndef OMIT_FEATURE
feature ::= KEPT.
%endif OMIT_FEATURE
%else
extension ::= DISABLED.
%ifdef NOT_ENABLED
omitted ::= NEVER.
%else
fallback ::= DEFAULT.
%endif
%endif
root ::= END.
LEMON;
        $enabled = (new LemonPreprocessor(new LemonCondition(['EXTENSION'])))->process($input);
        self::assertStringContainsString('extension ::= ENABLED.', $enabled);
        self::assertStringContainsString('feature ::= KEPT.', $enabled);
        self::assertStringNotContainsString('DISABLED', $enabled);
        self::assertStringNotContainsString('DEFAULT', $enabled);
        self::assertSame(substr_count($input, "\n"), substr_count($enabled, "\n"));
        $disabled = (new LemonPreprocessor())->process($input);
        self::assertStringContainsString('extension ::= DISABLED.', $disabled);
        self::assertStringContainsString('fallback ::= DEFAULT.', $disabled);
        self::assertStringNotContainsString('NEVER', $disabled);
    }

    public function testProcessHandlesSqliteUpdateDeleteLimitCondition(): void
    {
        $input = "%if LIMIT || CAPABLE\ncmd ::= DELETE ORDER BY.\n%else\ncmd ::= DELETE.\n%endif\n";
        self::assertSame("\n\n\ncmd ::= DELETE.\n\n", (new LemonPreprocessor())->process($input));
        self::assertSame("\ncmd ::= DELETE ORDER BY.\n\n\n\n", (new LemonPreprocessor(new LemonCondition(['CAPABLE'])))->process($input));
    }

    public function testProcessRejectsUnmatchedElse(): void
    {
        $this->expectException(RuntimeException::class);
        (new LemonPreprocessor())->process("%else\n");
    }

    public function testProcessRejectsRepeatedElse(): void
    {
        $this->expectException(RuntimeException::class);
        (new LemonPreprocessor())->process("%ifdef YES\n%else\n%else\n%endif\n");
    }

    public function testProcessRejectsUnterminatedConditionalsEvenWhenActive(): void
    {
        $this->expectException(RuntimeException::class);
        (new LemonPreprocessor())->process("%ifndef NO\ncmd ::= YES.\n");
    }
}
