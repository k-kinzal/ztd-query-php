<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\Mode;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Platform::class)]
#[Small]
final class PlatformTest extends TestCase
{
    public function testProfileFixesTheNewestReleaseByDefault(): void
    {
        self::assertSame(GrammarRelease::PostgreSql172, (new Platform())->profile(null, null, ParameterStyle::Native)->grammar);
        self::assertSame(GrammarRelease::PostgreSql166, (new Platform())->profile('pg-16.6', null, ParameterStyle::Native)->grammar);
    }

    public function testProfileRejectsASessionMode(): void
    {
        $this->expectExceptionMessage('PostgreSQL reads SQL under no session mode');
        (new Platform())->profile(null, new class () implements Mode {
            public function toString(): string
            {
                return 'ANSI';
            }
        }, ParameterStyle::Native);
    }

    public function testParserReadsNamedPlaceholdersOnlyUnderTheNamedStyle(): void
    {
        $platform = new Platform();
        $named = $platform->parser(new LanguageProfile(GrammarRelease::PostgreSql172, parameters: ParameterStyle::Named));
        self::assertSame('PARAM', $named->tokenize('SELECT :id')[1]->name);
        self::assertSame('pg-16.6', $platform->parser(new LanguageProfile(GrammarRelease::PostgreSql166))->version());
    }

    public function testProductionsListsTheProductionsOfTheRelease(): void
    {
        $platform = new Platform();
        self::assertContains('JsonType: JSON', $platform->productions(new LanguageProfile(GrammarRelease::PostgreSql172))->all());
        self::assertNotContains('JsonType: JSON', $platform->productions(new LanguageProfile(GrammarRelease::PostgreSql166))->all());
    }

    public function testLowerAnswersOneStatementPerWrittenStatement(): void
    {
        $platform = new Platform();
        $profile = new LanguageProfile(GrammarRelease::PostgreSql172);
        $statements = $platform->lower($platform->parser($profile)->parse(';SELECT 1;;SELECT 2;'), $profile, new Leaves());
        self::assertCount(2, $statements);
        self::assertInstanceOf(Select::class, $statements[0]);
    }

    public function testCodecQuotesWhatTheGrammarWouldReadDifferently(): void
    {
        $codec = (new Platform())->codec(new LanguageProfile(GrammarRelease::PostgreSql172));
        self::assertSame('"Select"', $codec->name(new Name('Select'), NameUse::Column));
    }

    public function testLeafKeysKeysAnIdentifierByItsDecodedName(): void
    {
        $keys = (new Platform())->leafKeys(new LanguageProfile(GrammarRelease::PostgreSql172));
        self::assertSame('name:foo', $keys->key(new Token(1, 'IDENT', 'FOO', 0), 'ColId: IDENT', 0));
    }

    public function testContextSearchesTheTemporarySchemaAndTheCatalogBeforeThePath(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        self::assertSame(['pg_temp', 'pg_catalog', 'public'], array_map(static fn (Name $schema): string => $schema->value, $context->searchPath));
        self::assertSame('public', $context->declarationSchema->value);
        self::assertSame(Comparison::Sensitive, $context->relationNames);
    }

    public function testContextKeepsThePlaceThePathGivesTheCatalog(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), ['app', 'pg_catalog'], [], false);
        self::assertSame(['pg_temp', 'app', 'pg_catalog'], array_map(static fn (Name $schema): string => $schema->value, $context->searchPath));
        self::assertSame('app', $context->declarationSchema->value);
        self::assertFalse($context->complete);
    }

    public function testStatementNamespaceIsTheStatementNamespaceOfThePackage(): void
    {
        self::assertSame('SqlSemantics\\Platform\\PostgreSql\\Statement\\', (new Platform())->statementNamespace());
    }
}
