<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\OutputPart;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Lexeme\FactorLexemeGenerator;

#[CoversClass(FactorLexemeGenerator::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(OutputPart::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator::class)]
final class FactorLexemeGeneratorTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerFactors')]
    public function testGenerateKeepsFactorPairsDistinctWithinTheirUser(string $action, bool $prior, ?string $rightValue, bool $sameUser, array $expected): void
    {
        $terminals = $prior ? [new TerminalOccurrence('AUTH_FACTOR_NUMBER', 10, [0, 1], ['alter_user', 'factor'])] : [];
        $terminals[] = new TerminalOccurrence($action, 11, [0], ['alter_user']);
        $origin = new TerminalOccurrence('AUTH_FACTOR_NUMBER', 12, [0, 2], ['alter_user', 'factor']);
        $terminals[] = $origin;
        $rightOrigin = new TerminalOccurrence('AUTH_FACTOR_NUMBER', 13, [$sameUser ? 0 : 9, 3], ['alter_user', 'factor']);
        $right = new ResolvedOutput($rightValue === null ? [] : [new OutputPart(new Lexeme($rightValue, 'number', $rightOrigin, 'source'), '', 'candidate')]);
        $input = new LexemeInput(new TerminalSequence($terminals), count($terminals) - 1, $right);
        $result = (new FactorLexemeGenerator())->generate($input);
        self::assertNotNull($result);
        $candidates = [...$result->sequences()];
        self::assertSame($expected, array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, $candidates));
        self::assertSame(array_fill(0, count($expected), $origin), array_map(static fn ($candidate) => $candidate->lexemes[0]->origin, $candidates));
    }

    /**
     * @return list<array{string, bool, string|null, bool, list<string>}>
     */
    public static function providerFactors(): array
    {
        return [['DROP', false, null, true, ['2', '3']], ['DROP', false, '2', true, ['3']], ['DROP', false, '3', true, ['2']], ['ADD', true, null, true, ['3']], ['ADD', false, '3', true, ['2']], ['MODIFY_SYM', true, null, true, ['2', '3']], ['DROP', false, '2', false, ['2', '3']]];
    }

    #[DataProvider('providerSpellings')]
    public function testGenerateAcceptsOnlyExactFactorSpellings(string $spelling, bool $valid): void
    {
        $input = new LexemeInput(TerminalSequence::fromNames(['AUTH_FACTOR_NUMBER']), 0, new ResolvedOutput(), $spelling);
        $result = (new FactorLexemeGenerator())->generate($input);
        self::assertNotNull($result);
        self::assertSame($valid ? [$spelling] : [], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
    }

    /**
     * @return list<array{string, bool}>
     */
    public static function providerSpellings(): array
    {
        return [['2', true], ['3', true], ['0', false], ['1', false], ['02', false], ['4', false]];
    }

    public function testGenerateLeavesOrdinaryNumbersToTheirGenerator(): void
    {
        self::assertNull((new FactorLexemeGenerator())->generate(new LexemeInput(TerminalSequence::fromNames(['NUM']), 0, new ResolvedOutput())));
    }
}
