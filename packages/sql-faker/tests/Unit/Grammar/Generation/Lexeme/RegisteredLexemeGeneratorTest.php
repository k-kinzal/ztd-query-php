<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\LexemeSequence;
use SqlFaker\Grammar\Generation\Lexeme\RegisteredLexemeGenerator;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\LexicalException;

#[CoversClass(RegisteredLexemeGenerator::class)]
#[UsesClass(LexemeGenerator::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
final class RegisteredLexemeGeneratorTest extends TestCase
{
    public function testGenerateKeepsAllRegisteredSpellingsAndTheAliasOccurrence(): void
    {
        $generator = new RegisteredLexemeGenerator(['NE' => ['!=', '<>']], 'lex.h', ['ALIAS' => 'NE']);
        $input = new LexemeInput(TerminalSequence::fromNames(['ALIAS']), 0, new ResolvedOutput());
        $candidates = [...$generator->generate($input)->sequences()];
        self::assertSame(['!=', '<>'], array_map(static fn (LexemeSequence $s): string => $s->lexemes[0]->text, $candidates));
        self::assertSame($input->terminal(), $candidates[0]->lexemes[0]->origin);
        self::assertSame('lex.h', $candidates[1]->lexemes[0]->definition);
    }

    public function testGenerateReportsAMissingRegistrationOnlyWhenItIsRequested(): void
    {
        $generator = new RegisteredLexemeGenerator(['KNOWN' => ['known']], 'table');
        self::assertCount(1, [...$generator->generate(new LexemeInput(TerminalSequence::fromNames(['KNOWN']), 0, new ResolvedOutput()))->sequences()]);
        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Missing registration for ABSENT in table');
        $generator->generate(new LexemeInput(TerminalSequence::fromNames(['ABSENT']), 0, new ResolvedOutput()));
    }
}
