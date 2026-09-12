<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\ResolvedOutput;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Lexeme\SizeNumberLexemeGenerator;

#[CoversClass(SizeNumberLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\IntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\WordDomain::class)]
final class SizeNumberLexemeGeneratorTest extends TestCase
{
    #[DataProvider('providerSizes')]
    public function testGeneratePreservesOnlyValidSizeSpellings(string $spelling, bool $valid): void
    {
        $sequence = TerminalSequence::fromNames(['SIZE_NUMBER']);
        $result = (new SizeNumberLexemeGenerator())->generate(new LexemeInput($sequence, 0, new ResolvedOutput(), $spelling));
        self::assertNotNull($result);
        $candidates = [...$result->sequences()];
        self::assertSame($valid ? [$spelling] : [], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, $candidates));
        self::assertSame($valid ? [$sequence->terminals[0]] : [], array_map(static fn ($candidate) => $candidate->lexemes[0]->origin, $candidates));
    }

    /**
     * @return list<array{string, bool}>
     */
    public static function providerSizes(): array
    {
        return [['0K', true], ['1m', true], ['2147483647G', true], ['0001k', true], ['`10M`', true], ['2147483648K', false], ['18446744073709551616G', false], ['-1M', false], ['10T', false], ['1.5M', false], ['10MB', false], ['`10M', false], ['10M`', false], ['name', false], ['', false]];
    }

    public function testGenerateProvidesEveryScaleAndLeavesOtherTerminalsUnclaimed(): void
    {
        $generator = new SizeNumberLexemeGenerator();
        $result = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['SIZE_NUMBER']), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertSame(['1K', '1M', '1G'], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
        self::assertNull($generator->generate(new LexemeInput(TerminalSequence::fromNames(['IDENT']), 0, new ResolvedOutput())));
    }
}
