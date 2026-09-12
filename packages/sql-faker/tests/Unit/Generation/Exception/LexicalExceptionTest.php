<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Exception;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Exception\LexicalException;
use SqlFaker\Generation\Value\RandomCharacters;
use SqlFaker\Grammar\Resource\SqlVersion;
use SqlFaker\Grammar\Resource\SqlVersionRegistry;
use SqlFaker\MySql\Generation\LexicalGrammar;
use SqlFaker\MySql\Generation\Tokenization\KeywordIndex;
use SqlFaker\MySql\Generation\Tokenization\MySqlTokenizer;
use SqlFaker\MySql\Generation\Value\LiteralGenerator;

#[CoversClass(LexicalException::class)]
#[UsesClass(LexicalGrammar::class)]
#[UsesClass(LiteralGenerator::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Value\LiteralGenerator::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Value\LiteralGenerator::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(KeywordIndex::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Tokenization\KeywordIndex::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Tokenization\KeywordIndex::class)]
#[UsesClass(RandomCharacters::class)]
#[UsesClass(SqlVersionRegistry::class)]
#[UsesClass(MySqlTokenizer::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Tokenization\MySqlQuoting::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\FixedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\IntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeInput::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\MatchingLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\ValueLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\SequenceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Output\CandidateResolver::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\ResolvedOutput::class)]
#[UsesClass(\SqlFaker\Generation\Output\ReverseLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Output\CombinedSpacingRule::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalSequence::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\VersionCase::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\VersionedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\DefinitionFactory::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\KeywordLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Plan\GenerationPlan::class)]
#[UsesClass(\SqlFaker\Generation\Plan\ProductionPattern::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\OutputPart::class)]
#[UsesClass(\SqlFaker\Generation\Output\SqlSerializer::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\KeywordPhraseSpacingRule::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeBoundary::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\CloneAddressSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\FunctionSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\QualifiedNameSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\VariableSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\BoundedIntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\ReplicationTablePatternLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\SizeNumberLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\FactorLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\PrecisionLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\ChoiceDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Value\DollarQuotedDomain::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Value\DollarQuotedDomain::class)]
#[UsesClass(\SqlFaker\Generation\Output\BoundaryCompletion::class)]
#[UsesClass(\SqlFaker\Generation\Value\ValueChoices::class)]
#[UsesClass(\SqlFaker\Generation\Choice\BytePlanCompiler::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\CharsetLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\CharsetValueLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Value\IdentifierDomain::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Value\IdentifierDomain::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Value\IdentifierDomain::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Value\QuotedDomain::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Value\QuotedDomain::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Value\QuotedDomain::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Value\RadixDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\WordDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\Utf8::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\LexicalDefinition::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Lexeme\LexicalDefinition::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\LexicalDefinition::class)]
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
