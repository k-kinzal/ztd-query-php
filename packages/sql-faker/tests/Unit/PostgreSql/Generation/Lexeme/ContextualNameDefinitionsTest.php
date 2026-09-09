<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Lexeme\ContextualNameDefinitions;

#[CoversClass(ContextualNameDefinitions::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
final class ContextualNameDefinitionsTest extends TestCase
{
    public function testCreateRestrictsTheContextualDomain(): void
    {
        $generator = (new ContextualNameDefinitions())->create();
        $result = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['POLICY_MODE']), 0, new ResolvedOutput(), null));
        self::assertNotNull($result);
        self::assertSame(['PERMISSIVE', 'RESTRICTIVE'], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['POLICY_MODE']), 0, new ResolvedOutput(), 'unknown'));
        self::assertNotNull($invalid);
        self::assertSame([], [...$invalid->sequences()]);
    }

    public function testDomainPreservesCompatibleExplicitCase(): void
    {
        $generator = (new ContextualNameDefinitions())->domain('MODE', ['VALID'], 'checked_rule');
        $result = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['MODE']), 0, new ResolvedOutput(), 'valid'));
        self::assertNotNull($result);
        self::assertSame('valid', [...$result->sequences()][0]->lexemes[0]->text);
    }

    public function testCreateIncludesEveryGrammarEncodingAndPartitionStrategy(): void
    {
        $generator = (new ContextualNameDefinitions())->create();
        $encoding = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['JSON_ENCODING']), 0, new ResolvedOutput()));
        $strategy = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['PARTITION_STRATEGY']), 0, new ResolvedOutput()));
        self::assertNotNull($encoding);
        self::assertNotNull($strategy);
        self::assertSame(['UTF8', 'UTF16', 'UTF32'], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$encoding->sequences()]));
        self::assertSame(['LIST', 'RANGE', 'HASH'], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$strategy->sequences()]));
    }
}
