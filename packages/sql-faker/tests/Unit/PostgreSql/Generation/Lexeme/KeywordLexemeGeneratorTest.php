<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\OutputPart;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Lexeme\KeywordLexemeGenerator;

#[CoversClass(KeywordLexemeGenerator::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexemeGenerator::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(OutputPart::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\RegisteredLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\PostgreSql\PgLookahead::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalException::class)]
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
