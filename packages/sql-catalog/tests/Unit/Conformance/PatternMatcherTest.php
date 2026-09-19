<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Conformance\PatternMatcher;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(PatternMatcher::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class PatternMatcherTest extends TestCase
{
    public function testMatchesARecognisableStatementThroughItsFormatting(): void
    {
        $matcher = new PatternMatcher();
        self::assertTrue($matcher->matches(TextPattern::fromText('SELECT 1'), "SELECT\n  1"));
        self::assertFalse($matcher->matches(TextPattern::fromText('SELECT 1'), 'SELECT 2'));
    }

    public function testMatchesAStatementThroughAGap(): void
    {
        $pattern = TextPattern::fromText('SELECT * FROM users WHERE 1 = 1')
            ->concat(TextPattern::fromHole(new TextHole(Origin::Loop, TypeShape::unknown())));
        $matcher = new PatternMatcher();
        self::assertTrue($matcher->matches($pattern, 'SELECT * FROM users WHERE 1 = 1 AND a = ?'));
        self::assertFalse($matcher->matches($pattern, 'SELECT * FROM orders WHERE 1 = 1'));
    }

    public function testToRegexTurnsGapsIntoWildcards(): void
    {
        $pattern = TextPattern::fromHole(new TextHole(Origin::Loop, TypeShape::unknown()))
            ->concat(TextPattern::fromText(' FROM t'));
        self::assertSame('/^.*?FROM\s*t$/su', (new PatternMatcher())->toRegex($pattern));
    }

    public function testQuoteLetsASpaceMatchAnyRunOfWhitespace(): void
    {
        self::assertSame('a\\s*b', (new PatternMatcher())->quote('a b'));
    }

    public function testNormalizeCollapsesWhitespace(): void
    {
        self::assertSame('SELECT 1', (new PatternMatcher())->normalize("  SELECT\n\t1 "));
    }
}
