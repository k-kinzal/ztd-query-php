<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Lexeme\ValueDefinitions;

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
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator::class)]
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
        $valid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['IDENT']), 0, new ResolvedOutput(), '"a""b"'));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['IDENT']), 0, new ResolvedOutput(), '"unclosed'));
        self::assertNotNull($valid);
        self::assertNotNull($invalid);
        self::assertSame('"a""b"', [...$valid->sequences()][0]->lexemes[0]->text);
        self::assertSame([], [...$invalid->sequences()]);
    }

    public function testStringsChecksSourceDelimiterOrValueBoundaries(): void
    {
        $generator = (new ValueDefinitions())->strings();
        $valid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['SCONST']), 0, new ResolvedOutput(), "'a''b'"));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['SCONST']), 0, new ResolvedOutput(), "'unclosed"));
        self::assertNotNull($valid);
        self::assertNotNull($invalid);
        self::assertSame("'a''b'", [...$valid->sequences()][0]->lexemes[0]->text);
        self::assertSame([], [...$invalid->sequences()]);
    }

    public function testNumbersChecksSourceDelimiterOrValueBoundaries(): void
    {
        $generator = (new ValueDefinitions())->numbers();
        $valid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['Op']), 0, new ResolvedOutput(), '?&'));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['Op']), 0, new ResolvedOutput(), '/*'));
        self::assertNotNull($valid);
        self::assertNotNull($invalid);
        self::assertSame('?&', [...$valid->sequences()][0]->lexemes[0]->text);
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
        return [['IDENT'], ['UIDENT'], ['PARAM'], ['SCONST'], ['USCONST'], ['BCONST'], ['XCONST'], ['ICONST'], ['FCONST'], ['Op']];
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
            ['FLOAT_PRECISION_NUMBER', '53', ['53']],
            ['FLOAT_PRECISION_NUMBER', '54', []],
            ['FLOAT_PRECISION_NUMBER', '0', []],
            ['COLUMN_POSITION_NUMBER', '32767', ['32767']],
            ['COLUMN_POSITION_NUMBER', '32768', []],
            ['COLUMN_POSITION_NUMBER', '0', []],
            ['SCONST', "'ordinary'", ["'ordinary'"]],
            ['SCONST', "'with\0nul'", []],
            ['USCONST', "U&'ordinary'", ["U&'ordinary'"]],
            ['USCONST', "U&'with\0nul'", []],
            ['UIDENT', 'u&"a""b"', ['u&"a""b"']],
            ['UIDENT', 'u&"unterminated', []],
            ['PARAM', '$17', ['$17']],
            ['PARAM', '$', []],
            ['SCONST', 'E\'a\\\'b\'', ['E\'a\\\'b\'']],
            ['SCONST', '\'unclosed', []],
            ['USCONST', 'u&\'a\'\'b\'', ['u&\'a\'\'b\'']],
            ['USCONST', 'u\'wrong prefix\'', []],
            ['BCONST', 'b\'001\'', ['b\'001\'']],
            ['BCONST', 'b\'02\'', []],
            ['XCONST', 'x\'f\'', ['x\'f\'']],
            ['XCONST', 'x\'g\'', []],
            ['ICONST', '1_000', ['1_000']],
            ['ICONST', '1__000', []],
            ['FCONST', '1', []],
            ['FCONST', '2147483647', []],
            ['FCONST', '2147483648', ['2147483648']],
            ['FCONST', '.5e+7', ['.5e+7']],
            ['FCONST', '.5e', []],
            ['Op', '?@', ['?@']],
            ['Op', '/*', []],
            ['Op', '|-', ['|-']],
            ['Op', '--', []],
        ];
    }
    #[DataProvider('providerDollarStrings')]
    public function testStringsRecognizesDollarDelimitersLiterally(string $value, bool $valid): void
    {
        $result = (new ValueDefinitions())->strings()->generate(new LexemeInput(TerminalSequence::fromNames(['SCONST']), 0, new ResolvedOutput(), $value));
        self::assertNotNull($result);
        self::assertSame($valid ? [$value] : [], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
    }

    /**
     * @return iterable<array{string, bool}>
     */
    public static function providerDollarStrings(): iterable
    {
        yield ['$$text$$', true];
        yield ['$tag$text$tag$', true];
        yield ['$_0$a\'b$_0$', true];
        yield ['$tag$text$other$', false];
        yield ['$0$text$0$', false];
        yield ['$$a$$b$$', false];
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

        yield ['IDENT', $minimum, ['_sf']];
        yield ['IDENT', [], ['"' . str_repeat('猫', 63) . '"']];
        yield ['IDENT', [1, 0], ['_sf' . str_repeat('$', 59)]];
        yield ['UIDENT', $minimum, ['U&"a"']];
        yield ['UIDENT', [], ['U&"' . str_repeat('猫', 63) . '"']];
        yield ['PARAM', $minimum, ['$1']];
        yield ['PARAM', [], ['$65535']];
        yield ['SCONST', $minimum, ["''"]];
        yield ['SCONST', [], ['$' . str_repeat('_', 17) . '$' . str_repeat('😀', 255) . '$' . str_repeat('_', 17) . '$']];
        yield ['SCONST', [1, 0], ["'" . str_repeat('😀', 255) . "'"]];
        yield ['SCONST', [1, 1], ["E'" . str_repeat('😀', 255) . "'"]];
        yield ['USCONST', $minimum, ["U&''"]];
        yield ['USCONST', [], ["U&'" . str_repeat('😀', 255) . "'"]];
        yield ['BCONST', $minimum, ["B''"]];
        yield ['BCONST', [], ["B'" . str_repeat('1', 255) . "'"]];
        yield ['XCONST', $minimum, ["X''"]];
        yield ['XCONST', [], ["X'" . str_repeat('F', 255) . "'"]];
        yield ['FLOAT_PRECISION_NUMBER', $minimum, ['1']];
        yield ['FLOAT_PRECISION_NUMBER', [], [$padding . '53']];
        yield ['COLUMN_POSITION_NUMBER', $minimum, ['1']];
        yield ['COLUMN_POSITION_NUMBER', [], [$padding . '32767']];
        yield ['ICONST', $minimum, ['0']];
        yield ['ICONST', [], [$padding . '2147483647']];
        yield ['FCONST', $minimum, ['2147483648', '1.5', '.5', '1e2']];
        yield ['FCONST', [], [$padding . str_repeat('9', 65), $padding . '18446744073709551615.' . str_repeat('9', 30) . 'E-' . $padding . '308']];
        yield ['FCONST', [0, 1, 0], ['2147483648', $padding . '18446744073709551615.' . str_repeat('9', 30)]];
        yield ['Op', $minimum, ['?']];
        yield ['Op', [], ['?' . str_repeat('~', 31)]];

        foreach (range(1, 127) as $byte) {
            $plain = str_replace("'", "''", chr($byte));
            $escaped = str_replace('\\', '\\\\', $plain);
            yield ['SCONST', [1, 0, 1, $byte - 1], ["'" . $plain . "'"]];
            yield ['SCONST', [1, 1, 1, $byte - 1], ["E'" . $escaped . "'"]];
            yield ['USCONST', [1, 1, $byte - 1], ["U&'" . $escaped . "'"]];
        }
    }
}
