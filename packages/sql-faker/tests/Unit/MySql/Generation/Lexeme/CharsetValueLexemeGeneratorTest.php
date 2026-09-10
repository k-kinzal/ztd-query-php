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
use SqlFaker\MySql\Generation\Lexeme\CharsetValueLexemeGenerator;

#[CoversClass(CharsetValueLexemeGenerator::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ValueLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ChoiceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\DollarQuotedDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\OutputPart::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ValueChoices::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\CharsetLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\RadixDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\Utf8::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\WordDomain::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\DefinitionFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Generation\Value\IdentifierDomain::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Generation\Value\QuotedDomain::class)]
final class CharsetValueLexemeGeneratorTest extends TestCase
{
    #[DataProvider('providerBinaryTokens')]
    public function testGenerateSamplesCharsetCompatibleBytesWithoutRestrictingOrdinaryBinaryValues(string $terminal): void
    {
        $generator = (new \SqlFaker\MySql\Generation\Lexeme\DefinitionFactory())->binary();
        $ordinary = TerminalSequence::fromNames([$terminal]);
        $introduced = TerminalSequence::fromNames(['UNDERSCORE_CHARSET', $terminal]);
        $unrestricted = $generator->generate(new LexemeInput($ordinary, 0, new ResolvedOutput(), values: new \SqlFaker\Grammar\Generation\Value\ValueChoices(static fn (int $count): int => $count - 1)));
        $character = $generator->generate(new LexemeInput($introduced, 1, new ResolvedOutput(), values: new \SqlFaker\Grammar\Generation\Value\ValueChoices(static fn (int $count): int => $count - 1)));
        self::assertNotNull($unrestricted);
        self::assertNotNull($character);
        $charset = new \SqlFaker\MySql\Generation\Lexeme\CharsetLexemeGenerator();
        self::assertFalse($charset->accepts('_utf8mb4', $charset->bytes([...$unrestricted->sequences()][0]->lexemes[0]->text)));
        self::assertTrue($charset->accepts('_ascii', $charset->bytes([...$character->sequences()][0]->lexemes[0]->text)));
    }

    /**
     * @return list<array{string}>
     */
    public static function providerBinaryTokens(): array
    {
        return [['HEX_NUM'], ['BIN_NUM']];
    }

}
