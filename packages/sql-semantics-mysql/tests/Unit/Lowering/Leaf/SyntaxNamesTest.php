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
        $text = new Node('ident_or_text', 0, [new Node('ident', 0, [new Node('IDENT_sys', 0, [new Token(0, 'IDENT', $name, 0)])])]);

        self::assertEquals($expected, (new SyntaxNames())->completed(new Node($rule, 0, [$text]), $lowering));
    }
}
