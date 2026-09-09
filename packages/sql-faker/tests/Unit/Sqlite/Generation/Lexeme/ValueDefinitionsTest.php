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
}
