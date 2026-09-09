<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

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
use SqlFaker\PostgreSql\LexicalGrammar;
use SqlFaker\PostgreSql\PgLookahead;
use SqlFaker\PostgreSql\PgTokenizer;

#[CoversClass(LexicalGrammar::class)]
#[CoversClass(RandomStringGenerator::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(LexicalKeywordIndex::class)]
#[UsesClass(RandomCharacters::class)]
#[UsesClass(SqlVersionRegistry::class)]
#[UsesClass(PgLookahead::class)]
#[UsesClass(PgTokenizer::class)]
#[UsesClass(\SqlFaker\PostgreSql\PgQuoting::class)]
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
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\LexemeBoundary::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionCase::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Lexeme\DefinitionFactory::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Lexeme\HashBoundLexemeGenerator::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Lexeme\KeywordLexemeGenerator::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\ProductionPattern::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ChoiceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\DollarQuotedDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\BoundaryCompletion::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\IdentifierDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\OperatorDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\QuotedDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\WordDomain::class)]
final class LexicalGrammarTest extends TestCase
{
    public function testGenerateQuotedIdentifierWritesWhatTheLexerReadsBackAsAnIdentifier(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');

        self::assertSame(['IDENT'], $lexical->tokenize($lexical->generateQuotedIdentifier(3, 3)));
    }

    public function testGenerateStringLiteralWritesWhatTheLexerReadsBackAsAString(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');

        self::assertSame(['SCONST'], $lexical->tokenize($lexical->generateStringLiteral(3, 3)));
    }

    public function testGenerateIntegerLiteralWritesWhatTheLexerReadsBackAsAnInteger(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');

        self::assertSame(['ICONST'], $lexical->tokenize($lexical->generateIntegerLiteral(10, 10)));
    }

    public function testGenerateDecimalLiteralWritesWhatTheLexerReadsBackAsAFloat(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');

        self::assertSame(['FCONST'], $lexical->tokenize($lexical->generateDecimalLiteral(4, 2)));
    }

    public function testGenerateFloatLiteralWritesWhatTheLexerReadsBackAsAFloat(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');

        self::assertSame(['FCONST'], $lexical->tokenize($lexical->generateFloatLiteral(4, 2, 2, 2)));
    }

    public function testGenerateHexLiteralWritesWhatTheLexerReadsBackAsAHexString(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');

        self::assertSame(['XCONST'], $lexical->tokenize($lexical->generateHexLiteral(4, 4)));
    }

    public function testGenerateBinaryLiteralWritesWhatTheLexerReadsBackAsABitString(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');

        self::assertSame(['BCONST'], $lexical->tokenize($lexical->generateBinaryLiteral(4, 4)));
    }

    public function testGenerateDollarQuotedStringWritesWhatTheLexerReadsBackAsAString(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');

        self::assertSame(['SCONST'], $lexical->tokenize($lexical->generateDollarQuotedString(3, 3)));
    }

    public function testGenerateParameterMarkerWritesWhatTheLexerReadsBackAsAParameter(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');

        self::assertSame(['PARAM'], $lexical->tokenize($lexical->generateParameterMarker(2, 2)));
    }

    public function testVersionReportsTheReleaseTheProfileWasBuiltFor(): void
    {
        self::assertSame('pg-17.2', (new LexicalGrammar(Factory::create(), 'pg-17.2'))->version());
    }

    /**
     * @param Closure(LexicalGrammar): string $withDefaults
     * @param Closure(LexicalGrammar): string $withExplicitBounds
     */
    #[DataProvider('providerPublicLexemeDefaults')]
    public function testPublicLexemeDefaultBounds(Closure $withDefaults, Closure $withExplicitBounds): void
    {
        $faker = Factory::create();
        $grammar = new LexicalGrammar($faker, 'pg-17.2');

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
            static fn (LexicalGrammar $grammar): string => $grammar->generateQuotedIdentifier(1, 63),
        ];
        yield 'string' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateStringLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateStringLiteral(1, 255),
        ];
        yield 'integer' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateIntegerLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateIntegerLiteral(1, 2147483647),
        ];
        yield 'decimal' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateDecimalLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateDecimalLiteral(10, 2),
        ];
        yield 'float' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateFloatLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateFloatLiteral(10, 2, -307, 308),
        ];
        yield 'hex' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateHexLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateHexLiteral(1, 16),
        ];
        yield 'binary' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateBinaryLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateBinaryLiteral(1, 64),
        ];
        yield 'dollar quoted string' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateDollarQuotedString(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateDollarQuotedString(1, 255),
        ];
        yield 'parameter marker' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateParameterMarker(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateParameterMarker(1, 99),
        ];
    }

    public function testTokenizesAllProblematicLiteralAndOperatorFamilies(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'pg-17.2');
        $sql = <<<'SQL'
SELECT "values", 'a''b', E'a\b', $$FROM ?$$, $tag$WHERE$tag$, B'101', X'af', $1, data ?| tags
/* UPDATE */ -- DELETE
FROM items
SQL;

        self::assertSame([
            'SELECT', 'IDENT', ',', 'SCONST', ',', 'SCONST', ',', 'SCONST', ',', 'SCONST', ',', 'BCONST', ',',
            'XCONST', ',', 'PARAM', ',', 'DATA_P', 'Op', 'IDENT', 'FROM', 'IDENT',
        ], $lexical->tokenize($sql));
    }

    public function testAppliesParserLookaheadOnlyInItsVersionedContext(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'pg-17.2');

        self::assertSame(['WITH_LA', 'TIME'], $lexical->tokenize('WITH TIME'));
        self::assertSame(['WITH', 'RETURNS'], $lexical->tokenize('WITH RETURNS'));
    }

    public function testNormalizeLookaheadSettlesDerivedTokensFromTheirFollowers(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'pg-17.2');

        self::assertSame(
            ['WITH', 'IDENT', 'WITH_LA', 'TIME', 'FORMAT', 'IDENT', 'FORMAT_LA', 'JSON'],
            $lexical->normalizeLookahead([
                'WITH_LA', 'IDENT', 'WITH', 'TIME', 'FORMAT_LA', 'IDENT', 'FORMAT', 'JSON',
            ]),
        );
    }

    public function testTokenizesCommentsAdjacentToOperators(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'pg-17.2');
        $sql = "SELECT a=/* outer /* inner */ outer */b, c/-- line\nd, e///* block */f";

        self::assertSame(
            ['SELECT', 'IDENT', '=', 'IDENT', ',', 'IDENT', '/', 'IDENT', ',', 'IDENT', 'Op', 'IDENT'],
            $lexical->tokenize($sql),
        );
    }

    public function testRealizesLookaheadTokenWithRequiredFollower(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'pg-17.2');
        $sql = $lexical->realize(['WITH_LA', 'TIME', 'ZONE']);

        self::assertSame(['WITH_LA', 'TIME', 'ZONE'], $lexical->tokenize($sql));
    }

    public function testRejectsLookaheadTokenWithoutRequiredFollower(): void
    {
        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('No compatible lexeme for WITH_LA');

        (new LexicalGrammar(Factory::create(), 'pg-17.2'))->realize(['WITH_LA', 'RETURNS']);
    }
    public function testRealizeSequenceHonorsThePlannedStringWithoutRequiringTrivia(): void
    {
        $grammar = new LexicalGrammar(Factory::create(), 'pg-17.2');
        $plan = GenerationPlan::all()->withLexemes(['SCONST' => ["'a''b'"]]);
        self::assertSame("'a''b'", $grammar->realizeSequence(TerminalSequence::fromNames(['SCONST']), $plan));
    }

    public function testIsNonOutputDoesNotConfuseOrdinaryValuesWithParserMarkers(): void
    {
        $grammar = new LexicalGrammar(Factory::create(), 'pg-17.2');
        self::assertFalse($grammar->isNonOutput('SCONST'));
        self::assertFalse($grammar->isNonOutput('UNIMPLEMENTED'));
    }

    public function testRealizeReportsAnUnimplementedTerminalAtItsActualUse(): void
    {
        $grammar = new LexicalGrammar(Factory::create(), 'pg-17.2');
        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('UNIMPLEMENTED');
        $grammar->realize(['UNIMPLEMENTED']);
    }

    public function testResolveSequenceExposesTheChosenCandidateAndItsOutput(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'pg-17.2');
        $output = $lexical->resolveSequence(TerminalSequence::fromNames(['ICONST']), null, static fn (int $count): int => $count - 1);
        self::assertSame('2', $output->parts[0]->lexeme->text);
        self::assertCount(1, $output->candidates);
        self::assertSame('', $output->parts[0]->separator);
    }
}
