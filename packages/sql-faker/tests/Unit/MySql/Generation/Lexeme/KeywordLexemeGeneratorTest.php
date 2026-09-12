<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Exception\LexicalException;
use SqlFaker\Generation\Lexeme\Lexeme;
use SqlFaker\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\OutputPart;
use SqlFaker\Generation\Lexeme\ResolvedOutput;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Lexeme\KeywordLexemeGenerator;

#[CoversClass(KeywordLexemeGenerator::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexemeGenerator::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(OutputPart::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
final class KeywordLexemeGeneratorTest extends TestCase
{
    public function testGenerateSeparatesFunctionUseFromIdentifierUse(): void
    {
        $generator = new KeywordLexemeGenerator([], ['NOW_SYM' => ['NOW']]);
        $sequence = TerminalSequence::fromNames(['NOW_SYM', '(']);
        $right = new ResolvedOutput([new OutputPart(new Lexeme('(', 'symbol', $sequence->terminals[1], 'source'), '', 'open')]);
        $function = $generator->generate(new LexemeInput($sequence, 0, $right));
        self::assertNotNull($function);
        self::assertSame('function', [...$function->sequences()][0]->lexemes[0]->kind);
        $identifier = new TerminalOccurrence('NOW_SYM', 0, [1], ['ident']);
        $name = $generator->generate(new LexemeInput(new TerminalSequence([$identifier]), 0, $right));
        self::assertNotNull($name);
        self::assertSame('identifier', [...$name->sequences()][0]->lexemes[0]->kind);
    }

    public function testGenerateLeavesUndeclaredTerminalsUnclaimed(): void
    {
        $generator = new KeywordLexemeGenerator([], []);
        self::assertNull($generator->generate(new LexemeInput(TerminalSequence::fromNames(['UNKNOWN']), 0, new ResolvedOutput())));
    }
}
