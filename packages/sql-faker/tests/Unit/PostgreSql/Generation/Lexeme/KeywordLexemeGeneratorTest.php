<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Lexeme\Lexeme;
use SqlFaker\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\OutputPart;
use SqlFaker\Generation\Lexeme\ResolvedOutput;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Lexeme\KeywordLexemeGenerator;

#[CoversClass(KeywordLexemeGenerator::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexemeGenerator::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(OutputPart::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\RegisteredLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\PostgreSql\Lookahead\PgLookahead::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Exception\LexicalException::class)]
final class KeywordLexemeGeneratorTest extends TestCase
{
    public function testGenerateChecksTheLookaheadFollowerBeforeOfferingAnAlias(): void
    {
        $generator = new KeywordLexemeGenerator(['WITH' => ['WITH']]);
        $sequence = TerminalSequence::fromNames(['WITH_LA', 'TIME']);
        $right = new ResolvedOutput([new OutputPart(new Lexeme('TIME', 'keyword', $sequence->terminals[1], 'source'), '', 'time')]);
        self::assertSame('WITH', [...$generator->generate(new LexemeInput($sequence, 0, $right))->sequences()][0]->lexemes[0]->text);
        self::assertSame([], [...$generator->generate(new LexemeInput($sequence, 0, new ResolvedOutput()))->sequences()]);
    }
}
