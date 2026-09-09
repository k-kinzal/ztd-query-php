<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Lexeme\DollarStringDefinitions;

#[CoversClass(DollarStringDefinitions::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionCase::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ChoiceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\DollarQuotedDomain::class)]
final class DollarStringDefinitionsTest extends TestCase
{
    public function testCreateUsesTheFirstMatchingDelimiterAndRejectsAnUnclosedTag(): void
    {
        $generator = (new DollarStringDefinitions())->create('mysql-8.4.7');
        $valid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['DOLLAR_QUOTED_STRING_SYM']), 0, new ResolvedOutput(), '$tag$a$other$b$tag$'));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['DOLLAR_QUOTED_STRING_SYM']), 0, new ResolvedOutput(), '$tag$a$tag$b$tag$'));
        self::assertNotNull($valid);
        self::assertNotNull($invalid);
        self::assertCount(1, [...$valid->sequences()]);
        self::assertSame([], [...$invalid->sequences()]);
    }

    public function testVersionsDoesNotAssumeCompatibilityWithUnreviewedReleases(): void
    {
        $definitions = new DollarStringDefinitions();
        self::assertNotContains('mysql-8.0.44', $definitions->versions());
        self::assertContains('mysql-8.1.0', $definitions->versions());
        self::assertNull($definitions->create('mysql-9.2.0')->generate(new LexemeInput(TerminalSequence::fromNames(['DOLLAR_QUOTED_STRING_SYM']), 0, new ResolvedOutput())));
    }
}
