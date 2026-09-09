<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\LexemeSequence;
use SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\RegisteredLexemeGenerator;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Sqlite\Generation\Lexeme\WindowNameLexemeGenerator;

#[CoversClass(WindowNameLexemeGenerator::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexemeCandidates::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(LexemeSequence::class)]
#[UsesClass(MatchingLexemeGenerator::class)]
#[UsesClass(RegisteredLexemeGenerator::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
final class WindowNameLexemeGeneratorTest extends TestCase
{
    /**
     * @param non-empty-list<string> $names
     */
    #[DataProvider('providerNames')]
    public function testGenerateQuotesIndexedOnlyWhereWindowLookaheadRequiresAnIdentifier(array $names, string $expected, string $source): void
    {
        $input = new LexemeInput(TerminalSequence::fromNames($names), count($names) - 1, new ResolvedOutput());
        $generator = new WindowNameLexemeGenerator(new RegisteredLexemeGenerator(['INDEXED' => ['INDEXED'], 'JOIN_KW' => ['INNER']], 'registrations'));
        $candidates = $generator->generate($input);
        self::assertNotNull($candidates);
        $candidate = [...$candidates->sequences()][0];
        self::assertSame($expected, $candidate->lexemes[0]->text);
        self::assertSame($input->terminal(), $candidate->lexemes[0]->origin);
        self::assertSame([$source], [...$candidate->sources()]);
    }

    /**
     * @return iterable<string, array{non-empty-list<string>, string, string}>
     */
    public static function providerNames(): iterable
    {
        yield 'over' => [['OVER', 'INDEXED'], '"INDEXED"', 'registrations:INDEXED'];
        yield 'window' => [['WINDOW', 'INDEXED'], '"INDEXED"', 'registrations:INDEXED'];
        yield 'other name' => [['OVER', 'JOIN_KW'], 'INNER', 'registrations:INNER'];
        yield 'ordinary indexed' => [['SELECT', 'INDEXED'], 'INDEXED', 'registrations:INDEXED'];
        yield 'standalone' => [['INDEXED'], 'INDEXED', 'registrations:INDEXED'];
    }

    public function testGenerateRetainsNonApplicability(): void
    {
        $generator = new WindowNameLexemeGenerator(new MatchingLexemeGenerator('OTHER', new RegisteredLexemeGenerator([], 'registrations')));
        self::assertNull($generator->generate(new LexemeInput(TerminalSequence::fromNames(['OVER', 'INDEXED']), 1, new ResolvedOutput())));
    }

    public function testGeneratePropagatesMissingRegistrationData(): void
    {
        $generator = new WindowNameLexemeGenerator(new RegisteredLexemeGenerator([], 'registrations'));
        $this->expectException(LexicalException::class);
        $generator->generate(new LexemeInput(TerminalSequence::fromNames(['OVER', 'INDEXED']), 1, new ResolvedOutput()));
    }
}
