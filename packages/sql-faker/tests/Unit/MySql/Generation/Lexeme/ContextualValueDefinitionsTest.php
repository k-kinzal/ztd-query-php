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
use SqlFaker\MySql\Generation\Lexeme\ContextualValueDefinitions;
use SqlFaker\Sqlite\Generation\Lexeme\ValueDefinitions;

#[CoversClass(ContextualValueDefinitions::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(ValueDefinitions::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionCase::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\BoundedIntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\ReplicationTablePatternLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\FactorLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ChoiceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\DollarQuotedDomain::class)]
final class ContextualValueDefinitionsTest extends TestCase
{
    public function testCreateRestrictsTheContextualDomain(): void
    {
        $generator = (new ContextualValueDefinitions())->create('mysql-8.4.7');
        $result = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['ROTATE_KEY_ENGINE']), 0, new ResolvedOutput(), null));
        self::assertNotNull($result);
        self::assertSame(['INNODB', 'BINLOG'], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['ROTATE_KEY_ENGINE']), 0, new ResolvedOutput(), 'unknown'));
        self::assertNotNull($invalid);
        self::assertSame([], [...$invalid->sequences()]);
    }

    public function testDomainPreservesCompatibleExplicitCase(): void
    {
        $generator = (new ContextualValueDefinitions())->domain('MODE', ['VALID'], 'checked_rule');
        $result = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['MODE']), 0, new ResolvedOutput(), 'valid'));
        self::assertNotNull($result);
        self::assertSame('valid', [...$result->sequences()][0]->lexemes[0]->text);
    }
    public function testCreateEnumeratesOnlySourceAcceptedTernaryValues(): void
    {
        $generator = (new ContextualValueDefinitions())->create('mysql-8.4.7');
        $input = new LexemeInput(TerminalSequence::fromNames(['TERNARY_OPTION_NUMBER']), 0, new ResolvedOutput());
        $result = $generator->generate($input);
        self::assertNotNull($result);
        self::assertSame(['0', '1'], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
        $invalid = $generator->generate(new LexemeInput($input->terminals, 0, new ResolvedOutput(), '2'));
        self::assertNotNull($invalid);
        self::assertSame([], [...$invalid->sequences()]);
    }

    /**
     * @param list<string> $spellings
     */
    #[DataProvider('providerContextualDomains')]
    public function testCreatePreservesTheVersionedParserValueDomains(string $version, string $terminal, array $spellings): void
    {
        $result = (new ContextualValueDefinitions())->create($version)->generate(new LexemeInput(TerminalSequence::fromNames([$terminal]), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertSame($spellings, array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
    }

    /**
     * @return iterable<array{string, string, list<string>}>
     */
    public static function providerContextualDomains(): iterable
    {
        yield ['mysql-5.7.44', 'ROTATE_KEY_ENGINE', ['INNODB']];
        yield ['mysql-5.7.44', 'REPLICATION_TABLE_PATTERN', ["'db.table'", "'db.%'", "'%.table'"]];
        foreach (['mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'] as $version) {
            yield [$version, 'REPLICATION_TABLE_PATTERN', ["'db.table'", "'db.%'", "'%.table'"]];
            yield [$version, 'REDO_ENGINE', ['INNODB']];
            yield [$version, 'REDO_LOG_NAME', ['REDO_LOG']];
            yield [$version, 'LOAD_COUNT_NAME', ['COUNT']];
            yield [$version, 'LOAD_SOURCE_COUNT', ['1']];
            yield [$version, 'REPLICATION_FLAG_NUMBER', ['0', '1']];
            yield [$version, 'BINLOG_RESET_INDEX', ['1', '2000000000', "X'01'"]];
        }
    }

    #[DataProvider('providerLiteralNames')]
    public function testDomainTreatsNamesLiterallyAndRetainsTheirSourceDefinition(string $spelling): void
    {
        $generator = (new ContextualValueDefinitions())->domain('NAME', ['A.B', 'C~D'], 'names');
        $input = TerminalSequence::fromNames(['NAME']);
        $result = $generator->generate(new LexemeInput($input, 0, new ResolvedOutput(), $spelling));
        self::assertNotNull($result);
        $candidates = [...$result->sequences()];
        self::assertCount(1, $candidates);
        self::assertSame($spelling, $candidates[0]->lexemes[0]->text);
        self::assertSame('sql/sql_yacc.yy:names', $candidates[0]->lexemes[0]->definition);
        $invalid = $generator->generate(new LexemeInput($input, 0, new ResolvedOutput(), 'axb'));
        self::assertNotNull($invalid);
        self::assertSame([], [...$invalid->sequences()]);
    }
    /**
     * @return iterable<array{string}>
     */
    public static function providerLiteralNames(): iterable
    {
        yield ['a.b'];
        yield ['C~D'];
    }
}
