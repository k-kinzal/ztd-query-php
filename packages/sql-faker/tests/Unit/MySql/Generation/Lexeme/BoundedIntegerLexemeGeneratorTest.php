<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Lexeme\BoundedIntegerLexemeGenerator;

#[CoversClass(BoundedIntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class BoundedIntegerLexemeGeneratorTest extends TestCase
{
    public function testAcceptsUsesBothConfiguredBoundsForDecimalAndHexadecimalValues(): void
    {
        $delay = new BoundedIntegerLexemeGenerator('DELAY', 0, 2147483647, ['0'], 'delay');
        self::assertTrue($delay->accepts('0'));
        self::assertTrue($delay->accepts("X''"));
        self::assertTrue($delay->accepts('0x7fffffff'));
        self::assertFalse($delay->accepts('0x80000000'));
        $pages = new BoundedIntegerLexemeGenerator('PAGES', 1, 65535, ['1'], 'pages');
        self::assertFalse($pages->accepts('0'));
        self::assertTrue($pages->accepts('65535'));
        self::assertTrue($pages->accepts('0xffff'));
        self::assertFalse($pages->accepts('65536'));
        self::assertFalse($pages->accepts('0x10000'));
    }

    #[DataProvider('providerIndices')]
    public function testAcceptsUnsignedFormsWithinTheSourceInterval(string $spelling, bool $valid): void
    {
        self::assertSame($valid, (new BoundedIntegerLexemeGenerator('BINLOG_RESET_INDEX', 1, 2000000000, ['1', '2000000000', '0x1'], 'sql/sql_yacc.yy:source_reset_options'))->accepts($spelling));
    }

    /**
     * @return list<array{string, bool}>
     */
    public static function providerIndices(): array
    {
        return [['1', true], ['0001', true], ['2000000000', true], ['0x77359400', true], ["X'77359400'", true], ["x'0001'", true], ['0x000000001', true], ['0', false], ['2000000001', false], ['0x77359401', false], ["X'77359401'", false], ['0xfffffffff', false], ['0x000', false], ["X''", false], ["X'1'", false], ['0X1', false], ['1e0', false], ['-1', false], ['', false]];
    }

    public function testGenerateKeepsTheExplicitHexSpellingAndOrigin(): void
    {
        $sequence = TerminalSequence::fromNames(['BINLOG_RESET_INDEX']);
        $generator = new BoundedIntegerLexemeGenerator('BINLOG_RESET_INDEX', 1, 2000000000, ['1', '2000000000', '0x1'], 'sql/sql_yacc.yy:source_reset_options');
        $result = $generator->generate(new LexemeInput($sequence, 0, new ResolvedOutput(), "X'0001'"));
        self::assertNotNull($result);
        self::assertSame("X'0001'", [...$result->sequences()][0]->lexemes[0]->text);
        self::assertSame($sequence->terminals[0], [...$result->sequences()][0]->lexemes[0]->origin);
        $invalid = $generator->generate(new LexemeInput($sequence, 0, new ResolvedOutput(), '0'));
        self::assertNotNull($invalid);
        self::assertSame([], [...$invalid->sequences()]);
    }

    public function testGenerateProvidesBoundaryRepresentativesWithoutClaimingOtherNumbers(): void
    {
        $generator = new BoundedIntegerLexemeGenerator('BINLOG_RESET_INDEX', 1, 2000000000, ['1', '2000000000', '0x1'], 'sql/sql_yacc.yy:source_reset_options');
        $result = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['BINLOG_RESET_INDEX']), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertSame(['1', '2000000000', '0x1'], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
        self::assertNull($generator->generate(new LexemeInput(TerminalSequence::fromNames(['NUM']), 0, new ResolvedOutput())));
    }
}
