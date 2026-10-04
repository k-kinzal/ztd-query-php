<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\Sqlite\Platform;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;

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

        self::assertSame('`select`', $codec->name(new Name('select'), \SqlSemantics\Contract\NameUse::Column));
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

    public function testStatementNamespaceBelongsToThePlatformTheFacadeSelects(): void
    {
        self::assertInstanceOf(Platform::class, Platforms::of('sqlite'));
    }
}
