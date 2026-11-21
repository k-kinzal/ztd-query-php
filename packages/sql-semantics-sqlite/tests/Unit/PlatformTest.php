<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\Sqlite\Model\Lowering\Leaves;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Platform;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;

#[CoversClass(Platform::class)]
#[Medium]
final class PlatformTest extends TestCase
{
    public function testProfileFixesTheDefaultReleaseWithoutASessionMode(): void
    {
        $profile = (new Platform())->profile(null, null, ParameterStyle::Native);

        self::assertSame(GrammarRelease::Sqlite3472, $profile->grammar);
        self::assertSame(ParameterStyle::Native, $profile->parameters);
    }

    public function testProfileAcceptsTheShippedReleaseByNameAndKeepsTheParameterStyle(): void
    {
        $profile = (new Platform())->profile('sqlite-3.47.2', null, ParameterStyle::Named);

        self::assertSame(GrammarRelease::Sqlite3472, $profile->grammar);
        self::assertSame(ParameterStyle::Named, $profile->parameters);
        self::assertTrue($profile->compatibleWith((new Platform())->profile(null, null, ParameterStyle::Named)));
    }

    public function testParserParsesSqliteAndIsCreatedOncePerProfile(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $parser = $platform->parser($profile);

        self::assertSame($parser, $platform->parser($profile));
        self::assertSame('input', $parser->parse('SELECT 1')->name);
    }

    public function testProductionsAnswerTheSignaturesOfTheRelease(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $productions = $platform->productions($profile);
        $tree = $platform->parser($profile)->parse('SELECT 1');

        self::assertSame('input: cmdlist', $productions->signature($tree));
        self::assertContains('where_opt: WHERE expr', $productions->all());
        self::assertSame($productions, $platform->productions($profile));
    }

    public function testLowerAnswersTheStatementsOfATreeInOrder(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $leaves = new Leaves();
        $statements = $platform->lower($platform->parser($profile)->parse('SELECT a; SELECT b'), $profile, $leaves);

        self::assertCount(2, $statements);
        self::assertInstanceOf(Select::class, $statements[0]);
        self::assertInstanceOf(Select::class, $statements[1]);
        self::assertSame(['a', 'b'], array_map(static fn (object $leaf): string => $leaf instanceof Name ? $leaf->value : '', $leaves->all()));
    }

    public function testCodecSpellsNamesAsSqliteIdentifiers(): void
    {
        $platform = new Platform();
        $codec = $platform->codec($platform->profile(null, null, ParameterStyle::Native));

        self::assertSame('`select`', $codec->name(new Name('select'), NameUse::Column));
    }

    public function testLeafKeysKeysSqliteTokens(): void
    {
        $platform = new Platform();
        $keys = $platform->leafKeys($platform->profile(null, null, ParameterStyle::Native));

        self::assertSame('string:x', $keys->key(new \SqlParser\Lexer\Token(1, 'STRING', "'x'", 0), 'term: STRING', 0));
    }

    public function testContextSearchesTempThenMainByDefaultAndComparesNamesWithoutAsciiCase(): void
    {
        $platform = new Platform();
        $context = $platform->context($platform->profile(null, null, ParameterStyle::Native), null, [], true);

        self::assertSame(['temp', 'main'], array_map(static fn (Name $schema): string => $schema->value, $context->searchPath));
        self::assertSame('main', $context->declarationSchema->value);
        self::assertTrue($context->complete);
        self::assertSame(Comparison::AsciiInsensitive, $context->relationNames);
        self::assertSame(Comparison::AsciiInsensitive, $context->columnNames);
        self::assertSame([], $context->tables);
    }

    public function testContextKeepsTheAttachedSchemasAfterMainAndDropsAWrittenTemp(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $explicit = $platform->context($profile, ['TEMP', 'main', 'aux'], [], false);
        $plain = $platform->context($profile, ['main', 'aux'], [], false);

        self::assertSame(['temp', 'main', 'aux'], array_map(static fn (Name $schema): string => $schema->value, $explicit->searchPath));
        self::assertSame(['temp', 'main', 'aux'], array_map(static fn (Name $schema): string => $schema->value, $plain->searchPath));
        self::assertFalse($plain->complete);
    }

    public function testStatementNamespaceNamesTheSqliteStatementValues(): void
    {
        self::assertSame('SqlSemantics\\Platform\\Sqlite\\Statement\\', (new Platform())->statementNamespace());
        self::assertSame(Select::class, (new Platform())->statementNamespace() . 'Query\\Select');
    }

    public function testProfileFixesTheDefaultReleaseOfTheDatabase(): void
    {
        $profile = Platform::shared()->profile(null, null, ParameterStyle::Native);

        self::assertSame(GrammarRelease::Sqlite3472, $profile->grammar);
        self::assertSame(ParameterStyle::Native, $profile->parameters);
    }

    public function testProfileRefusesAReleaseTheDatabaseDoesNotShip(): void
    {
        $this->expectExceptionMessage('sqlite');

        Platform::shared()->profile('0.0.1', null, ParameterStyle::Native);
    }

    public function testParserAnswersTheSameParserForOneProfile(): void
    {
        $platform = Platform::shared();
        $profile = $platform->profile(null, null, ParameterStyle::Native);

        self::assertSame($platform->parser($profile), $platform->parser($profile));
    }

    public function testLowerTurnsAParseTreeIntoStatementsAndRecordsTheLeaves(): void
    {
        $platform = Platform::shared();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $leaves = new Leaves();

        $statements = $platform->lower($platform->parser($profile)->parse('SELECT a FROM t; SELECT 2'), $profile, $leaves);

        self::assertCount(2, $statements);
        self::assertInstanceOf(Select::class, $statements[0]);
        self::assertCount(1, $statements[0]->columns);
        self::assertCount(3, $leaves->all());
    }

    public function testProductionsSpellTheProductionOfARootNode(): void
    {
        $platform = Platform::shared();
        $profile = $platform->profile(null, null, ParameterStyle::Native);

        $tree = $platform->parser($profile)->parse('SELECT 1');

        self::assertSame('input: cmdlist', $platform->productions($profile)->signature($tree));
    }

    public function testCodecSpellsNamesOfTheProfile(): void
    {
        $platform = Platform::shared();
        $profile = $platform->profile(null, null, ParameterStyle::Native);

        self::assertSame('`a b`', $platform->codec($profile)->name(new Name('a b'), NameUse::Column));
    }

    public function testLeafKeysKeyTokensOfTheProfile(): void
    {
        $platform = Platform::shared();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $token = $platform->parser($profile)->tokenize("SELECT 'x'")[1];

        self::assertSame('string:x', $platform->leafKeys($profile)->key($token, 'term: STRING', 0));
    }

    public function testContextAppliesTheNameRulesOfTheDatabase(): void
    {
        $platform = Platform::shared();
        $profile = $platform->profile(null, null, ParameterStyle::Native);

        $context = $platform->context($profile, null, [], false);

        self::assertSame(Comparison::AsciiInsensitive, $context->columnNames);
        self::assertSame(['temp', 'main'], array_map(static fn (Name $name): string => $name->value, $context->searchPath));
        self::assertFalse($context->complete);
    }

    public function testStatementNamespaceClosesTheValueDomainOfTheDatabase(): void
    {
        self::assertSame('SqlSemantics\\Platform\\Sqlite\\Statement\\', Platform::shared()->statementNamespace());
        self::assertStringStartsWith(Platform::shared()->statementNamespace(), Select::class);
    }

    public function testSharedReusesTheDatabasePlatform(): void
    {
        self::assertSame(Platform::shared(), Platform::shared());
    }
}
