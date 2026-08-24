<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\GrammarParseException;

#[CoversClass(GrammarParseException::class)]
final class GrammarParseExceptionTest extends TestCase
{
    public function testNoRulesParsedNamesTheBisonGrammar(): void
    {
        self::assertSame(
            'No grammar rules parsed from the Bison grammar.',
            GrammarParseException::noRulesParsed('Bison')->getMessage(),
        );
    }

    public function testNoRulesParsedNamesTheLemonGrammar(): void
    {
        self::assertSame(
            'No grammar rules parsed from the Lemon grammar.',
            GrammarParseException::noRulesParsed('Lemon')->getMessage(),
        );
    }
}
