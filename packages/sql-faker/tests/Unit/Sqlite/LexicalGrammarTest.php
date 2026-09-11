<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use Closure;
use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\Lexical\LexicalKeywordIndex;
use SqlFaker\Grammar\Lexical\RandomCharacters;
use SqlFaker\Grammar\Lexical\RandomStringGenerator;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\Resource\SqlVersionRegistry;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\Sqlite\LexicalGrammar;
use SqlFaker\Sqlite\SqliteTokenizer;

#[CoversClass(LexicalGrammar::class)]
#[CoversClass(RandomStringGenerator::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(LexicalKeywordIndex::class)]
#[UsesClass(RandomCharacters::class)]
#[UsesClass(SqlVersionRegistry::class)]
#[UsesClass(SqliteTokenizer::class)]
#[UsesClass(\SqlFaker\Sqlite\SqliteQuoting::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeInput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ValueLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\RegisteredLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\CandidateResolver::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\OutputPart::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ResolvedOutput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\SqlSerializer::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionCase::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\DefinitionFactory::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\JoinLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\ProductionPattern::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\LexemeBoundary::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\JoinModifiers::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\WindowNameLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ChoiceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\DollarQuotedDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\BoundaryCompletion::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\IdentifierDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\QuotedDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\RepeatDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\WordDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexicalDefinition::class)]
final class LexicalGrammarTest extends TestCase
{
    public function testGenerateQuotedIdentifierWritesWhatTheLexerReadsBackAsAnIdentifier(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'sqlite-3.47.2');

        self::assertSame(['ID'], $lexical->tokenize($lexical->generateQuotedIdentifier(3, 3)));
    }

    public function testGenerateStringLiteralWritesWhatTheLexerReadsBackAsAString(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'sqlite-3.47.2');

        self::assertSame(['STRING'], $lexical->tokenize($lexical->generateStringLiteral(3, 3)));
    }

    public function testGenerateIntegerLiteralWritesWhatTheLexerReadsBackAsAnInteger(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'sqlite-3.47.2');

        self::assertSame(['INTEGER'], $lexical->tokenize($lexical->generateIntegerLiteral(10, 10)));
    }

    public function testGenerateDecimalLiteralWritesWhatTheLexerReadsBackAsAFloat(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'sqlite-3.47.2');

        self::assertSame(['FLOAT'], $lexical->tokenize($lexical->generateDecimalLiteral(4, 2)));
    }

    public function testVersionReportsTheReleaseTheProfileWasBuiltFor(): void
    {
        self::assertSame('sqlite-3.47.2', (new LexicalGrammar(Factory::create(), 'sqlite-3.47.2'))->version());
    }

    /**
     * @param Closure(LexicalGrammar): string $withDefaults
     * @param Closure(LexicalGrammar): string $withExplicitBounds
     */
    #[DataProvider('providerPublicLexemeDefaults')]
    public function testPublicLexemeDefaultBounds(Closure $withDefaults, Closure $withExplicitBounds): void
    {
        $faker = Factory::create();
        $grammar = new LexicalGrammar($faker, 'sqlite-3.47.2');

        $faker->seed(20_260_824);
        $generated = $withDefaults($grammar);

        $faker->seed(20_260_824);
        $explicit = $withExplicitBounds($grammar);

        self::assertNotSame('', $generated);
        self::assertSame($generated, $explicit);
    }

    /**
     * @return iterable<string, array{Closure(LexicalGrammar): string, Closure(LexicalGrammar): string}>
     */
    public static function providerPublicLexemeDefaults(): iterable
    {
        yield 'quoted identifier' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateQuotedIdentifier(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateQuotedIdentifier(1, 128),
        ];
        yield 'string' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateStringLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateStringLiteral(1, 255),
        ];
        yield 'integer' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateIntegerLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateIntegerLiteral(1, PHP_INT_MAX),
        ];
        yield 'decimal' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateDecimalLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateDecimalLiteral(15, 2),
        ];
    }

    public function testTokenizesQuotedIdentifiersStringsVariablesAndComments(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'sqlite-3.47.2');
        $sql = <<<'SQL'
SELECT "values", [select], `from`, 'FROM ''items''', X'af', ?, ?12, :name, @name, $name
/* UPDATE */ -- DELETE
FROM items
SQL;

        self::assertSame([
            'SELECT', 'ID', 'COMMA', 'ID', 'COMMA', 'ID', 'COMMA', 'STRING', 'COMMA', 'BLOB', 'COMMA',
            'VARIABLE', 'COMMA', 'VARIABLE', 'COMMA', 'VARIABLE', 'COMMA', 'VARIABLE', 'COMMA', 'VARIABLE',
            'FROM', 'ID',
        ], $lexical->tokenize($sql));
    }

    public function testUsesVersionedKeywordFamilies(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'sqlite-3.47.2');

        self::assertSame(['JOIN_KW', 'JOIN_KW', 'CTIME_KW'], $lexical->tokenize('LEFT CROSS CURRENT_TIMESTAMP'));
    }

    public function testTokenizesEveryNumberAndOperatorClass(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'sqlite-3.47.2');

        self::assertSame([
            'INTEGER', 'QNUMBER', 'INTEGER', 'FLOAT', 'FLOAT', 'FLOAT', 'FLOAT',
            'PTR', 'PTR', 'CONCAT', 'EQ', 'LE', 'NE', 'NE', 'GE', 'LSHIFT', 'RSHIFT',
            'LP', 'RP', 'SEMI', 'COMMA', 'DOT', 'EQ', 'LT', 'GT', 'PLUS', 'MINUS', 'STAR',
            'SLASH', 'REM', 'BITAND', 'BITOR', 'BITNOT',
        ], $lexical->tokenize(
            '0 1_0 0xAf 1.5 .5 1e2 1.e-2 '
            . '->> -> || == <= <> != >= << >> ( ) ; , . = < > + - * / % & | ~',
        ));
    }

    public function testTokenizesKeywordsCaseInsensitivelyAndSkipsEmbeddedComments(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'sqlite-3.47.2');

        self::assertSame(
            ['SELECT', 'ID', 'FROM', 'ID'],
            $lexical->tokenize("select-- comment\nname/* comment */from items"),
        );
    }

    public function testRealizesStrictTableOptionAsIdentifierToken(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'sqlite-3.47.2');

        $sql = $lexical->realize([LexicalGrammar::STRICT_TABLE_OPTION]);
        self::assertStringContainsString('STRICT', $sql);
        self::assertSame(['ID'], $lexical->tokenize($sql));
        self::assertSame(['ID'], $lexical->tokenize('STRICT'));
    }

    #[DataProvider('providerInvalidSql')]
    public function testRejectsInvalidSql(string $sql, string $message): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'sqlite-3.47.2');

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage($message);

        $lexical->tokenize($sql);
    }

    #[DataProvider('providerFixedTerminal')]
    public function testRealizeOfFixedTerminal(string $terminal, string $expected): void
    {
        $faker = Factory::create();
        $faker->seed(17);
        $lexical = new LexicalGrammar($faker, 'sqlite-3.47.2');

        self::assertSame($expected, $lexical->realize([$terminal]));
    }

    #[DataProvider('providerIdentifierTerminal')]
    public function testRealizeOfIdentifier(string $terminal): void
    {
        $faker = Factory::create();
        $faker->seed(17);
        $lexical = new LexicalGrammar($faker, 'sqlite-3.47.2');

        self::assertNotSame($terminal, $lexical->realize([$terminal]));
    }

    #[DataProvider('providerStringTerminal')]
    public function testRealizeOfString(string $terminal): void
    {
        $faker = Factory::create();
        $faker->seed(17);
        $lexical = new LexicalGrammar($faker, 'sqlite-3.47.2');

        self::assertStringStartsWith("'", $lexical->realize([$terminal]));
    }

    public function testRealizeOfGeneratedTerminals(): void
    {
        $faker = Factory::create();
        $faker->seed(17);
        $lexical = new LexicalGrammar($faker, 'sqlite-3.47.2');

        self::assertMatchesRegularExpression("/^X'[0-9a-f]*'$/", $lexical->realize(['BLOB']));
        self::assertMatchesRegularExpression('/^\d+$/', $lexical->realize(['number']));
        self::assertMatchesRegularExpression('/^\d+$/', $lexical->realize(['INTEGER']));
        self::assertMatchesRegularExpression('/^(?:\?\d*|[:@$][A-Za-z_][A-Za-z0-9_]*)$/', $lexical->realize(['VARIABLE']));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerInvalidSql(): iterable
    {
        yield 'bracket identifier' => ['[name', 'Unterminated SQLite bracket identifier.'];
        yield 'single quoted string' => ["'value", 'Unterminated SQLite quoted token'];
        yield 'double quoted identifier' => ['"name', 'Unterminated SQLite quoted token'];
        yield 'backtick quoted identifier' => ['`name', 'Unterminated SQLite quoted token'];
        yield 'block comment' => ['/* comment', 'Unterminated SQLite block comment.'];
        yield 'unsupported character' => ['SELECT \\', 'offset 7: SELECT \\'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerFixedTerminal(): iterable
    {
        yield 'QNUMBER' => ['QNUMBER', '1_0'];
        yield 'ANY' => ['ANY', 'name'];
        yield 'LP' => ['LP', '('];
        yield 'RP' => ['RP', ')'];
        yield 'SEMI' => ['SEMI', ';'];
        yield 'COMMA' => ['COMMA', ','];
        yield 'DOT' => ['DOT', '.'];
        yield 'EQ' => ['EQ', '='];
        yield 'LT' => ['LT', '<'];
        yield 'PLUS' => ['PLUS', '+'];
        yield 'MINUS' => ['MINUS', '-'];
        yield 'STAR' => ['STAR', '*'];
        yield 'BITAND' => ['BITAND', '&'];
        yield 'BITNOT' => ['BITNOT', '~'];
        yield 'CONCAT' => ['CONCAT', '||'];
        yield 'PTR' => ['PTR', '->'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerIdentifierTerminal(): iterable
    {
        yield 'ID' => ['ID'];
        yield 'id' => ['id'];
        yield 'idj' => ['idj'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStringTerminal(): iterable
    {
        yield 'ids' => ['ids'];
        yield 'STRING' => ['STRING'];
    }
    public function testRealizeSequenceHonorsThePlannedStringWithoutRequiringTrivia(): void
    {
        $grammar = new LexicalGrammar(Factory::create(), 'sqlite-3.47.2');
        $plan = GenerationPlan::all()->withLexemes(['STRING' => ["'a''b'"]]);
        self::assertSame("'a''b'", $grammar->realizeSequence(TerminalSequence::fromNames(['STRING']), $plan));
    }

    public function testIsNonOutputDoesNotConfuseOrdinaryValuesWithParserMarkers(): void
    {
        $grammar = new LexicalGrammar(Factory::create(), 'sqlite-3.47.2');
        self::assertFalse($grammar->isNonOutput('STRING'));
        self::assertFalse($grammar->isNonOutput('UNIMPLEMENTED'));
    }

    public function testRealizeReportsAnUnimplementedTerminalAtItsActualUse(): void
    {
        $grammar = new LexicalGrammar(Factory::create(), 'sqlite-3.47.2');
        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('UNIMPLEMENTED');
        $grammar->realize(['UNIMPLEMENTED']);
    }

}
