<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Lexical\LexicalKeywordIndex;
use SqlFaker\Grammar\Lexical\RandomCharacters;
use SqlFaker\Grammar\Lexical\RandomStringGenerator;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\Resource\SqlVersionRegistry;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\MySql\LexicalGrammar;
use SqlFaker\MySql\MySqlTokenizer;

#[CoversClass(LexicalException::class)]
#[UsesClass(LexicalGrammar::class)]
#[UsesClass(RandomStringGenerator::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(LexicalKeywordIndex::class)]
#[UsesClass(RandomCharacters::class)]
#[UsesClass(SqlVersionRegistry::class)]
#[UsesClass(MySqlTokenizer::class)]
#[UsesClass(\SqlFaker\MySql\MySqlQuoting::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeInput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ValueLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\SequenceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\CandidateResolver::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ResolvedOutput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionCase::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\DefinitionFactory::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\KeywordLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\GenerationPlan::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\ProductionPattern::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\OutputPart::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\SqlSerializer::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\KeywordPhraseSpacingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\LexemeBoundary::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\CloneAddressSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\FunctionSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\QualifiedNameSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\VariableSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\BoundedIntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\ReplicationTablePatternLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\SizeNumberLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\FactorLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\PrecisionLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ChoiceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\DollarQuotedDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\BoundaryCompletion::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\CharsetLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\CharsetValueLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\IdentifierDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\QuotedDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\RadixDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\WordDomain::class)]
final class LexicalExceptionTest extends TestCase
{
    public function testTokenizingUnsupportedInputReportsTheOffsetAndTheInput(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unsupported MySQL lexical input at offset 0:');

        $lexical->tokenize("\x00");
    }

    public function testRealizingAnUnknownTerminalReportsTheTerminal(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unsupported MySQL terminal for mysql-8.4.7: NOT_A_TERMINAL');

        $lexical->realize(['NOT_A_TERMINAL']);
    }

    public function testUnsupportedTerminalNamesTheTerminalAndTheProfile(): void
    {
        self::assertSame(
            'Unsupported MySQL terminal for mysql-8.4.7: NOT_A_TERMINAL',
            LexicalException::unsupportedTerminal('MySQL', 'mysql-8.4.7', 'NOT_A_TERMINAL')->getMessage(),
        );
    }

    public function testUnsupportedInputNamesTheOffsetAndTheText(): void
    {
        self::assertSame(
            'Unsupported SQLite lexical input at offset 3: abc',
            LexicalException::unsupportedInput('SQLite', 3, 'abc')->getMessage(),
        );
    }

    public function testNoProgressNamesTheOffsetTheReadGotStuckAt(): void
    {
        self::assertSame(
            'SQLite lexer made no progress at offset 3: abc',
            LexicalException::noProgress('SQLite', 3, 'abc')->getMessage(),
        );
    }

    public function testUnterminatedQuotedTokenNamesTheText(): void
    {
        self::assertSame(
            "Unterminated MySQL quoted token: 'abc",
            LexicalException::unterminatedQuotedToken('MySQL', "'abc")->getMessage(),
        );
    }

    public function testUnterminatedBracketIdentifierNamesTheDialect(): void
    {
        self::assertSame(
            'Unterminated SQLite bracket identifier.',
            LexicalException::unterminatedBracketIdentifier('SQLite')->getMessage(),
        );
    }

    public function testUnterminatedBlockCommentNamesTheDialect(): void
    {
        self::assertSame(
            'Unterminated PostgreSQL block comment.',
            LexicalException::unterminatedBlockComment('PostgreSQL')->getMessage(),
        );
    }

    public function testUnterminatedDollarQuotedStringNamesTheDialect(): void
    {
        self::assertSame(
            'Unterminated MySQL dollar-quoted string.',
            LexicalException::unterminatedDollarQuotedString('MySQL')->getMessage(),
        );
    }

    public function testLexemeDoesNotRealizeTerminalNamesBoth(): void
    {
        self::assertSame(
            'Requested MySQL lexeme does not realize IDENT: 42',
            LexicalException::lexemeDoesNotRealizeTerminal('MySQL', 'IDENT', '42')->getMessage(),
        );
    }

    public function testNoWitnessForLexemeNamesBoth(): void
    {
        self::assertSame(
            'MySQL lexical catalog has no IDENT witness for: 42',
            LexicalException::noWitnessForLexeme('MySQL', 'IDENT', '42')->getMessage(),
        );
    }

    public function testRenderedSubstitutesBytesJsonHasNoEncodingFor(): void
    {
        self::assertSame('["\\ufffd"]', LexicalException::rendered(["\xB1"]));
    }

    public function testRenderedWritesAnOrdinarySequenceAsJson(): void
    {
        self::assertSame('["IDENT"]', LexicalException::rendered(['IDENT']));
    }

    public function testRoundTripMismatchCarriesBothSequencesAndTheText(): void
    {
        $message = LexicalException::roundTripMismatch(
            'MySQL',
            'mysql-8.4.7',
            ['SELECT_SYM'],
            ['IDENT'],
            'select',
        )->getMessage();

        self::assertSame(
            "MySQL lexical round-trip failed for mysql-8.4.7.\n"
            . "Expected: [\"SELECT_SYM\"]\n"
            . "Actual: [\"IDENT\"]\n"
            . 'SQL: select',
            $message,
        );
    }
}
