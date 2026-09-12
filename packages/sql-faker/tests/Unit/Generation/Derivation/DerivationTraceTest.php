<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Derivation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Candidate\ChoiceLexemeGenerator;
use SqlFaker\Generation\Candidate\FixedLexemeGenerator;
use SqlFaker\Generation\Candidate\MatchingLexemeGenerator;
use SqlFaker\Generation\Candidate\RegisteredLexemeGenerator;
use SqlFaker\Generation\Candidate\SequenceLexemeGenerator;
use SqlFaker\Generation\Candidate\ValueLexemeGenerator;
use SqlFaker\Generation\Candidate\VersionCase;
use SqlFaker\Generation\Candidate\VersionedLexemeGenerator;
use SqlFaker\Generation\Derivation\CompletionCosts;
use SqlFaker\Generation\Derivation\Derivation;
use SqlFaker\Generation\Derivation\DerivationTrace;
use SqlFaker\Generation\Derivation\TerminationAnalyzer;
use SqlFaker\Generation\Derivation\TerminationCost;
use SqlFaker\Generation\Derivation\TokenGenerator;
use SqlFaker\Generation\Exception\GenerationException;
use SqlFaker\Generation\Exception\LexicalException;
use SqlFaker\Generation\Lexeme\Lexeme;
use SqlFaker\Generation\Lexeme\LexemeBoundary;
use SqlFaker\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\LexemeSequence;
use SqlFaker\Generation\Lexeme\OutputPart;
use SqlFaker\Generation\Lexeme\ResolvedOutput;
use SqlFaker\Generation\Lexeme\SpacingConstraint;
use SqlFaker\Generation\Lexeme\SpacingRule;
use SqlFaker\Generation\Output\CandidateResolver;
use SqlFaker\Generation\Output\CombinedSpacingRule;
use SqlFaker\Generation\Output\ReverseLexemeGenerator;
use SqlFaker\Generation\Output\SqlSerializer;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\Generation\Token\TokenRewriter;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\MySql\Generation\Spacing\KeywordPhraseSpacingRule;

#[CoversClass(DerivationTrace::class)]
#[UsesClass(RewriteRule::class)]
#[UsesClass(TokenGenerator::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(TokenRewriter::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(CandidateResolver::class)]
#[UsesClass(ReverseLexemeGenerator::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(OutputPart::class)]
#[UsesClass(SqlSerializer::class)]
#[UsesClass(VersionCase::class)]
#[UsesClass(VersionedLexemeGenerator::class)]
#[UsesClass(KeywordPhraseSpacingRule::class)]
#[UsesClass(CombinedSpacingRule::class)]
#[UsesClass(LexemeBoundary::class)]
#[UsesClass(SpacingRule::class)]
#[UsesClass(SpacingConstraint::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexemeGenerator::class)]
#[UsesClass(ValueLexemeGenerator::class)]
#[UsesClass(LexemeSequence::class)]
#[UsesClass(RegisteredLexemeGenerator::class)]
#[UsesClass(LexemeCandidates::class)]
#[UsesClass(SequenceLexemeGenerator::class)]
#[UsesClass(ChoiceLexemeGenerator::class)]
#[UsesClass(FixedLexemeGenerator::class)]
#[UsesClass(MatchingLexemeGenerator::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(CompletionCosts::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(TerminationAnalyzer::class)]
#[UsesClass(TerminationCost::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(GenerationException::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(\SqlFaker\Generation\Plan\ProductionPattern::class)]
#[UsesClass(\SqlFaker\Generation\Output\BoundaryCompletion::class)]
final class DerivationTraceTest extends TestCase
{
    public function testExpandPreservesRuleAncestorsAndProductionOrdinals(): void
    {
        $trace = new DerivationTrace('root');
        $trace->expand(0, new Production([new NonTerminal('child')]), 2);
        $trace->expand(0, new Production([new Terminal('IDENT'), new Terminal('.')]), 4);
        $result = $trace->terminals();
        self::assertSame(['IDENT', '.'], $result->names());
        self::assertSame(['root', 'child'], $result->terminals[0]->rules);
        self::assertSame([0, 1], $result->terminals[0]->ancestors);
        self::assertNull($result->productions[0]->parent);
        self::assertSame(0, $result->productions[1]->parent);
        self::assertSame(4, $result->productions[1]->ordinal);
    }

    public function testTerminalsRetainsASelectedEmptyProduction(): void
    {
        $trace = new DerivationTrace('root');
        $trace->expand(0, new Production([]), 1);
        $result = $trace->terminals();
        self::assertSame([], $result->terminals);
        self::assertCount(1, $result->productions);
        self::assertSame(1, $result->productions[0]->ordinal);
    }
}
