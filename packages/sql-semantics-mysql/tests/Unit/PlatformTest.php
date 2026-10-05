<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Platform::class)]
#[Medium]
final class PlatformTest extends TestCase
{
    public function testProfileFixesAShippedReleaseUnderAMode(): void
    {
        $platform = new Platform();
        $tagged = $platform->profile('mysql-5.7.44', Mode::fromString('ANSI_QUOTES'), ParameterStyle::Named);
        $bare = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $latest = $platform->profile(null, null, ParameterStyle::Native);

        self::assertSame(GrammarRelease::MySql5744, $tagged->grammar);
        self::assertTrue($tagged->lexical->ansiQuotes);
        self::assertSame(ParameterStyle::Named, $tagged->parameters);
        self::assertSame(GrammarRelease::MySql5744, $bare->grammar);
        self::assertFalse($bare->lexical->ansiQuotes);
        self::assertSame('mysql', $latest->grammar->database());
        self::assertFalse($tagged->compatibleWith($bare));
        self::assertTrue($bare->compatibleWith($platform->profile('mysql-5.7.44', new Mode(), ParameterStyle::Native)));
    }

    public function testProfileRejectsAReleaseThatIsNotShipped(): void
    {
        $this->expectExceptionMessage('No semantic profile exists for the selected grammar release.');

        (new Platform())->profile('mysql-5.5.62', null, ParameterStyle::Native);
    }

    public function testProfileRejectsAReleaseOfAnotherDatabase(): void
    {
        $this->expectExceptionMessage('No semantic profile exists for the selected grammar release.');

        (new Platform())->profile('sqlite-3.47.2', null, ParameterStyle::Native);
    }

    public function testProfileRejectsAModeOfAnotherDatabase(): void
    {
        $this->expectExceptionMessage('MySQL reads SQL under a MySQL session mode');

        new Semantics(Dialect::MySql, null, new class () implements \SqlSemantics\Contract\Mode {
            public function toString(): string
            {
                return '';
            }
        });
    }

    public function testParserIsCreatedOncePerProfile(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.0.44', null, ParameterStyle::Native);
        $other = $platform->profile('mysql-8.0.44', Mode::fromString('PIPES_AS_CONCAT'), ParameterStyle::Native);

        self::assertSame($platform->parser($profile), $platform->parser($profile));
        self::assertNotSame($platform->parser($profile), $platform->parser($other));
        self::assertSame('mysql-8.0.44', $platform->parser($profile)->version());
    }

    public function testProductionsAnswerTheSignaturesOfTheRelease(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $signatures = $platform->productions($profile)->all();

        self::assertContains('select_init2: select_part2 union_clause', $signatures);
        self::assertNotContains('select_stmt: query_expression', $signatures);
        self::assertSame($platform->productions($profile), $platform->productions($profile));
    }

    public function testLowerAnswersTheStatementsOfATree(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $statements = $platform->lower($platform->parser($profile)->parse('SELECT 1;'), $profile, new Leaves());

        self::assertCount(1, $statements);
        self::assertInstanceOf(Select::class, $statements[0]);
        self::assertSame([], $platform->lower($platform->parser($profile)->parse(''), $profile, new Leaves()));
    }

    public function testCodecSpellsNamesForTheRelease(): void
    {
        $platform = new Platform();
        $codec = $platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native));

        self::assertSame('`order`', $codec->name(new Name('order'), NameUse::Column));
        self::assertSame('t', $codec->name(new Name('t'), NameUse::Relation));
        self::assertSame('SELECT `rank`, `order` FROM t', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('SELECT `rank`, `order` FROM t')->toString());
    }

    public function testLeafKeysReadTokensUnderTheLexicalSettings(): void
    {
        $platform = new Platform();

        $verbatim = $platform->leafKeys($platform->profile(null, Mode::fromString('NO_BACKSLASH_ESCAPES'), ParameterStyle::Native));
        $plain = $platform->leafKeys($platform->profile(null, null, ParameterStyle::Native));

        self::assertSame('text:a\\nb', $verbatim->key(new Token(1, 'TEXT_STRING', "'a\\nb'", 0), 'text_literal: TEXT_STRING', 0));
        self::assertSame("text:a\nb", $plain->key(new Token(1, 'TEXT_STRING', "'a\\nb'", 0), 'text_literal: TEXT_STRING', 0));
    }

    public function testContextSearchesTheCurrentDatabaseOnly(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $implicit = $platform->context($profile, null, [], false);
        $explicit = $platform->context($profile, ['shop'], [], true);

        self::assertSame(SessionDatabase::UNNAMED, $implicit->searchPath[0]->value);
        self::assertFalse($implicit->complete);
        self::assertSame('shop', $explicit->searchPath[0]->value);
        self::assertSame('shop', $explicit->declarationSchema->value);
        self::assertTrue($explicit->complete);
        self::assertSame(Comparison::Sensitive, $explicit->relationNames);
        self::assertSame(Comparison::AsciiInsensitive, $explicit->columnNames);
    }

    public function testContextRejectsASearchPathOfSeveralDatabases(): void
    {
        $platform = new Platform();

        $this->expectExceptionMessage('MySQL searches an unqualified table name in the current database only.');

        $platform->context($platform->profile(null, null, ParameterStyle::Native), ['a', 'b'], [], true);
    }

    public function testStatementNamespaceNamesTheMySqlStatementValues(): void
    {
        self::assertSame('SqlSemantics\\Platform\\MySql\\Statement\\', (new Platform())->statementNamespace());
        self::assertStringStartsWith((new Platform())->statementNamespace(), Select::class);
        self::assertStringEndsWith('\\', (new Platform())->statementNamespace());
    }
}
