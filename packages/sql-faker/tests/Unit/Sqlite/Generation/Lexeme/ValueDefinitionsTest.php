<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Sqlite\Generation\Lexeme\ValueDefinitions;

#[CoversClass(ValueDefinitions::class)]
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
#[UsesClass(\SqlFaker\Grammar\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ChoiceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\DollarQuotedDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ValueChoices::class)]
final class ValueDefinitionsTest extends TestCase
{
    public function testCreateLeavesUnknownTerminalsUnclaimed(): void
    {
        self::assertNull((new ValueDefinitions())->create()->generate(new LexemeInput(TerminalSequence::fromNames(['UNKNOWN']), 0, new ResolvedOutput())));
    }

    public function testNamesChecksSourceDelimiterOrValueBoundaries(): void
    {
        $generator = (new ValueDefinitions())->names();
        $valid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['ID']), 0, new ResolvedOutput(), '"a""b"'));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['ID']), 0, new ResolvedOutput(), '"unclosed'));
        self::assertNotNull($valid);
        self::assertNotNull($invalid);
        self::assertSame('"a""b"', [...$valid->sequences()][0]->lexemes[0]->text);
        self::assertSame([], [...$invalid->sequences()]);
    }

    public function testStringsChecksSourceDelimiterOrValueBoundaries(): void
    {
        $generator = (new ValueDefinitions())->strings();
        $valid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['BLOB']), 0, new ResolvedOutput(), "X'00'"));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['BLOB']), 0, new ResolvedOutput(), "X'0'"));
        self::assertNotNull($valid);
        self::assertNotNull($invalid);
        self::assertSame("X'00'", [...$valid->sequences()][0]->lexemes[0]->text);
        self::assertSame([], [...$invalid->sequences()]);
    }

    public function testNumbersChecksSourceDelimiterOrValueBoundaries(): void
    {
        $generator = (new ValueDefinitions())->numbers();
        $valid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['QNUMBER']), 0, new ResolvedOutput(), '1_2'));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['QNUMBER']), 0, new ResolvedOutput(), '1__2'));
        self::assertNotNull($valid);
        self::assertNotNull($invalid);
        self::assertSame('1_2', [...$valid->sequences()][0]->lexemes[0]->text);
        self::assertSame([], [...$invalid->sequences()]);
    }

    #[DataProvider('providerValueTokens')]
    public function testCreateOffersACompleteCandidateForEverySourceValueToken(string $terminal): void
    {
        $result = (new ValueDefinitions())->create()->generate(new LexemeInput(TerminalSequence::fromNames([$terminal]), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertNotEmpty([...$result->sequences()]);
    }

    /**
     * @return list<array{string}>
     */
    public static function providerValueTokens(): array
    {
        return [['ID'], ['id'], ['idj'], ['ANY'], ['VARIABLE'], ['INTEGER'], ['number'], ['FLOAT'], ['QNUMBER'], ['STRING'], ['ids'], ['BLOB']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerLexicalForms')]
    public function testCreateRetainsValidSourceSpellingsAndRejectsMalformedOnes(string $terminal, string $spelling, array $expected): void
    {
        $result = (new ValueDefinitions())->create()->generate(new LexemeInput(TerminalSequence::fromNames([$terminal]), 0, new ResolvedOutput(), $spelling));
        self::assertNotNull($result);
        self::assertSame($expected, array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
    }

    /**
     * @return list<array{string, string, list<string>}>
     */
    public static function providerLexicalForms(): array
    {
        return [
            ['STRING', "'ordinary'", ["'ordinary'"]],
            ['STRING', "'with\0nul'", []],
            ['ids', "'ordinary'", ["'ordinary'"]],
            ['ids', "'with\0nul'", []],
            ['ID', '[a b]', ['[a b]']],
            ['ID', '[unclosed', []],
            ['id', '`a``b`', ['`a``b`']],
            ['id', '`unclosed', []],
            ['idj', '"a""b"', ['"a""b"']],
            ['idj', '"unclosed', []],
            ['ANY', 'alpha_7', ['alpha_7']],
            ['ANY', 'bad name', []],
            ['VARIABLE', '?23', ['?23']],
            ['VARIABLE', '?abc', []],
            ['INTEGER', '0XFE', ['0XFE']],
            ['INTEGER', '0xGG', []],
            ['number', '12', ['12']],
            ['number', '-12', []],
            ['FLOAT', '.75e-2', ['.75e-2']],
            ['FLOAT', '.75e', []],
            ['QNUMBER', '12_345_6', ['12_345_6']],
            ['QNUMBER', '12__3', []],
            ['ids', '\'a\'\'b\'', ['\'a\'\'b\'']],
            ['ids', '\'unclosed', []],
            ['BLOB', 'x\'01af\'', ['x\'01af\'']],
            ['BLOB', 'X\'abc\'', []],
        ];
    }

    /**
     * @param list<int> $decisions
     * @param list<string> $expected
     */
    #[DataProvider('providerConstructedBoundaries')]
    public function testCreateConstructsScannerBoundaryValues(string $terminal, array $decisions, array $expected): void
    {
        $values = new \SqlFaker\Grammar\Generation\Value\ValueChoices(static function (int $count) use (&$decisions): int {
            return array_shift($decisions) ?? $count - 1;
        });
        $result = (new ValueDefinitions())->create()->generate(new LexemeInput(TerminalSequence::fromNames([$terminal]), 0, new ResolvedOutput(), values: $values));
        self::assertNotNull($result);
        self::assertSame($expected, array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
    }

    /**
     * @return iterable<array{string, list<int>, list<string>}>
     */
    public static function providerConstructedBoundaries(): iterable
    {
        $minimum = [1, ...array_fill(0, 512, 0)];
        $padding = str_repeat('0', 16);

        foreach (['ID', 'id', 'idj', 'ANY'] as $terminal) {
            yield [$terminal, $minimum, ['"a"']];
            yield [$terminal, [], ['"' . str_repeat('猫', 64) . '"']];
        }
        yield ['VARIABLE', $minimum, ['?1']];
        yield ['VARIABLE', [], [':v' . str_repeat('$', 63)]];
        yield ['VARIABLE', [1, 0], ['?32766']];
        yield ['INTEGER', $minimum, ['0']];
        yield ['INTEGER', [], [$padding . '9223372036854775807']];
        yield ['number', $minimum, ['0']];
        yield ['number', [], [$padding . '9223372036854775807']];
        yield ['FLOAT', $minimum, ['0.']];
        yield ['FLOAT', [], [$padding . '18446744073709551615.' . str_repeat('9', 30) . 'E-' . $padding . '308']];
        yield ['FLOAT', [1, 0], [$padding . '18446744073709551615.' . str_repeat('9', 30)]];
        yield ['QNUMBER', $minimum, ['0_0']];
        yield ['QNUMBER', [], [$padding . '2147483647_' . $padding . '2147483647']];
        foreach (['STRING', 'ids'] as $terminal) {
            yield [$terminal, $minimum, ["''"]];
            yield [$terminal, [], ["'" . str_repeat('😀', 255) . "'"]];
        }
        yield ['BLOB', $minimum, ["X''"]];
        yield ['BLOB', [], ["X'" . str_repeat('F', 254) . "'"]];

        foreach (range(1, 127) as $byte) {
            $encoded = str_replace("'", "''", chr($byte));
            yield ['STRING', [1, 1, $byte - 1], ["'" . $encoded . "'"]];
            yield ['ids', [1, 1, $byte - 1], ["'" . $encoded . "'"]];
        }
    }
}
