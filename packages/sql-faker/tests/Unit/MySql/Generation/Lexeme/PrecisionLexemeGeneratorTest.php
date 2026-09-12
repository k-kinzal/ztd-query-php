<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Lexeme\Lexeme;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\OutputPart;
use SqlFaker\Generation\Lexeme\ResolvedOutput;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Lexeme\PrecisionLexemeGenerator;

#[CoversClass(PrecisionLexemeGenerator::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(OutputPart::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\IntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Value\IntegerDomain::class)]
final class PrecisionLexemeGeneratorTest extends TestCase
{
    #[DataProvider('providerWidths')]
    public function testGenerateEnforcesPrecisionAndScaleTogether(string $name, int $scale, string $requested, bool $valid): void
    {
        $origin = new TerminalOccurrence($name, 10, [1], ['precision']);
        $scaleOrigin = new TerminalOccurrence('NUMERIC_SCALE_NUMBER', 11, [1], ['precision']);
        $right = new ResolvedOutput([new OutputPart(new Lexeme((string) $scale, 'number', $scaleOrigin, 'source'), '', 'candidate')]);
        $input = new LexemeInput(new TerminalSequence([$origin]), 0, $right, $requested);
        $result = (new PrecisionLexemeGenerator())->generate($input);
        self::assertNotNull($result);
        $candidates = [...$result->sequences()];
        self::assertSame($valid ? [$requested] : [], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, $candidates));
        self::assertSame($valid ? [$origin] : [], array_map(static fn ($candidate) => $candidate->lexemes[0]->origin, $candidates));
    }

    /**
     * @return list<array{string, int, string, bool}>
     */
    public static function providerWidths(): array
    {
        return [['DECIMAL_DIGITS_NUMBER', 0, '0', true], ['DECIMAL_DIGITS_NUMBER', 1, '0', false], ['DECIMAL_DIGITS_NUMBER', 2, '1', false], ['DECIMAL_DIGITS_NUMBER', 30, '030', true], ['DECIMAL_DIGITS_NUMBER', 30, '65', true], ['DECIMAL_DIGITS_NUMBER', 30, '66', false], ['FLOAT_DIGITS_NUMBER', 0, '0', true], ['FLOAT_DIGITS_NUMBER', 2, '1', false], ['FLOAT_DIGITS_NUMBER', 30, '255', true], ['FLOAT_DIGITS_NUMBER', 0, '256', false], ['FLOAT_DIGITS_NUMBER', 0, '1.0', false]];
    }

    public function testGenerateUsesOnlyTheScaleInTheSameOccurrence(): void
    {
        $origin = new TerminalOccurrence('DECIMAL_DIGITS_NUMBER', 10, [1], ['precision']);
        $foreign = new TerminalOccurrence('NUMERIC_SCALE_NUMBER', 11, [2], ['precision']);
        $scale = new TerminalOccurrence('NUMERIC_SCALE_NUMBER', 12, [1], ['precision']);
        $right = new ResolvedOutput([new OutputPart(new Lexeme('30', 'number', $foreign, 'source'), '', 'a'), new OutputPart(new Lexeme('2', 'number', $scale, 'source'), '', 'b')]);
        $result = (new PrecisionLexemeGenerator())->generate(new LexemeInput(new TerminalSequence([$origin]), 0, $right));
        self::assertNotNull($result);
        self::assertSame(['2', '65'], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
        $missing = (new PrecisionLexemeGenerator())->generate(new LexemeInput(new TerminalSequence([$origin]), 0, new ResolvedOutput()));
        self::assertNotNull($missing);
        self::assertSame([], [...$missing->sequences()]);
        self::assertNull((new PrecisionLexemeGenerator())->generate(new LexemeInput(TerminalSequence::fromNames(['NUM']), 0, $right)));
    }
}
