<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Leaf\SyntaxNames;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

#[CoversClass(SyntaxNames::class)]
#[Medium]
final class SyntaxNamesTest extends TestCase
{
    public function testCompletedRetainsLegacyLeadingDotWarnings(): void
    {
        $platform = new Platform();
        $profile = new LanguageProfile(GrammarRelease::MySql5744);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $tree = $platform->parser($profile)->parse('SELECT .t.a FROM .t');

        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::DotColumn, (new SyntaxNames())->completed($tree->find('simple_ident_q')[0], $lowering));
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::DotTable, (new SyntaxNames())->completed($tree->find('table_ident')[0], $lowering));
    }

    /**
     * @return iterable<string, array{string, string, ParseFailure|null}>
     */
    public static function providerNames(): iterable
    {
        yield 'unknown collation' => ['collation_name', 'missing', new ParseFailure(new UnknownCollation('missing'), true)];
        yield 'unknown charset' => ['charset_name', 'missing', new ParseFailure(new UnknownCharset('missing'), true)];
        yield 'charset synonym' => ['old_or_new_charset_name', 'missing', new ParseFailure(new UnknownCharset('missing'), true)];
        yield 'known collation' => ['collation_name', 'utf8mb4_bin', null];
        yield 'known charset' => ['charset_name', 'utf8mb4', null];
        yield 'empty definition collation' => ['collation_name', '', new ParseFailure(new UnknownCollation(''), true)];
        yield 'empty charset aborts' => ['charset_name', '', new ParseFailure(new UnknownCharset(''), true)];
        yield 'unrelated name' => ['ident', 'missing', null];
    }

    #[DataProvider('providerNames')]
    public function testCompletedKeepsNameLookupAndAbortSemantics(string $rule, string $name, ?ParseFailure $expected): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $lowering = new Lowering((new Platform())->productions($profile), new Leaves(), $profile);
        $text = new Node('ident_or_text', 0, [new Node('ident', 0, [new Node('IDENT_sys', 0, [new Token(0, $name === '' ? 'IDENT_QUOTED' : 'IDENT', $name === '' ? '``' : $name, 0)])])]);

        self::assertEquals($expected, (new SyntaxNames())->completed(new Node($rule, 0, [$text]), $lowering));
    }
    public function testUnquotedUsesTheIdentifierProductionAndRelease(): void
    {
        $names = new SyntaxNames();
        $unquoted = new Node('ident_keywords_unambiguous', 0, [new Token(0, 'FULL', 'full', 0)]);
        $quoted = new Node('IDENT_sys', 0, [new Token(0, 'IDENT_QUOTED', '`FULL`', 0)]);
        $qualified = new Node('IDENT_sys', 0, [new Token(0, 'IDENT', 'FULL', 0)]);

        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::UnquotedFull, $names->unquoted($unquoted, GrammarRelease::MySql847));
        self::assertNull($names->unquoted($unquoted, GrammarRelease::MySql5744));
        self::assertNull($names->unquoted($quoted, GrammarRelease::MySql847));
        self::assertNull($names->unquoted($qualified, GrammarRelease::MySql847));
    }

    public function testCompletedRetainsIdentifierWarningsBeforeInputRefusals(): void
    {
        $sql = 'SHOW COLLATION WHERE FULL .x COLLATE missing = CAST(1 AT LOCAL AS DATETIME)';
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $warnings = (new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->completed(new \SqlParser\MySql\MySqlParser($profile->grammar->value), $sql, strlen($sql), $profile);

        self::assertEquals([\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::UnquotedFull, new ParseFailure(new UnknownCollation('missing')), new ParseFailure(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet('AT LOCAL'))], array_column($warnings, 1));
    }

    public function testAccountKeepsTheCompletedNameConditionBeforeLaterSyntax(): void
    {
        $platform = new Platform();
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $tree = $platform->parser($profile)->parse("DROP USER 'u'@'bad@host', CURRENT_USER");
        $rule = new SyntaxNames();
        self::assertEquals(new ParseFailure(new \SqlSemantics\Platform\MySql\Statement\Account\Problem\AccountNameFailure('bad@host', true), true), $rule->account($tree->find('user')[0], $lowering));
        self::assertNull($rule->account($tree->find('user')[1], $lowering));
        $sql = "DROP USER 'u'@'bad@host',";
        $warnings = (new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->completed(new \SqlParser\MySql\MySqlParser($profile->grammar->value), $sql, strlen($sql), $profile);
        self::assertEquals([new ParseFailure(new \SqlSemantics\Platform\MySql\Statement\Account\Problem\AccountNameFailure('bad@host', true), true)], array_column($warnings, 1));
    }

}
