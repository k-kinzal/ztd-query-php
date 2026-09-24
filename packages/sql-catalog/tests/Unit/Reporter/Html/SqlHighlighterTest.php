<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\StatementPart;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\SqlHighlighter;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(SqlHighlighter::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(StatementPart::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class SqlHighlighterTest extends TestCase
{
    public function testRenderMarksTheResolvedRunsAndTheGapsBetweenThem(): void
    {
        $pattern = TextPattern::fromSegments([
            new LiteralText('SELECT * FROM t WHERE id = '),
            new TextHole(Origin::External, TypeShape::unknown(), '$_GET["id"]'),
        ]);

        self::assertSame(
            '<span class="tok-kw">SELECT</span> * <span class="tok-kw">FROM</span> t <span class="tok-kw">WHERE</span> id = <span class="hole tone-danger" title="T'
                . 'his is a gap: external input fills it. Written as $_GET[&quot;id&quot;].">{$}</span>',
            (new SqlHighlighter())->render(StatementPart::of($pattern)),
        );
    }

    public function testInlineWritesTheStatementOnOneLine(): void
    {
        $parts = StatementPart::of(TextPattern::fromSegments([
            new LiteralText("SELECT\n\t1 FROM "),
            new TextHole(Origin::Property, TypeShape::unknown()),
        ]));

        self::assertStringStartsWith('<span class="tok-kw">SELECT</span> <span class="tok-num">1</span> <span class="tok-kw">FROM</span> <span class="hole', (new SqlHighlighter())->inline($parts));
    }

    public function testRenderSaysSoWhenThereIsNothingToShow(): void
    {
        self::assertSame('<span class="none">(empty)</span>', (new SqlHighlighter())->render(StatementPart::of(TextPattern::empty())));
    }

    public function testHighlightEscapesWithoutBreakingWhatItEscaped(): void
    {
        self::assertSame(
            '<span class="tok-kw">VALUES</span> (<span class="tok-str">&#039;a&#039;</span>)',
            (new SqlHighlighter())->highlight("VALUES ('a')"),
        );
    }

    public function testHighlightMarksCommentsNumbersAndPlaceholders(): void
    {
        self::assertSame(
            '<span class="tok-com">-- note</span>' . "\n" . '<span class="tok-num">42</span> <span class="tok-var">:id</span> <span class="tok-id">`t`</span>',
            (new SqlHighlighter())->highlight('-- note' . "\n" . '42 :id `t`'),
        );
    }

    public function testCapturedKeepsOnlyTheNamedCaptures(): void
    {
        self::assertSame(
            ['word' => 'SELECT'],
            (new SqlHighlighter())->captured([0 => ['SELECT', 0], 'word' => ['SELECT', 0], 1 => ['SELECT', 0]]),
        );
    }

    public function testTokenWritesAnUnknownWordAsPlainText(): void
    {
        self::assertSame('users', (new SqlHighlighter())->token(['word' => 'users']));
    }

    public function testTokenWritesAKeywordAsOne(): void
    {
        self::assertSame('<span class="tok-kw">FROM</span>', (new SqlHighlighter())->token(['word' => 'FROM']));
    }

    /**
     * @return list<array{string, bool}>
     */
    public static function providerIsKeyword(): array
    {
        return [['select', true], ['WHERE', true], ['users', false], ['', false]];
    }

    #[DataProvider('providerIsKeyword')]
    public function testIsKeywordRecognisesTheWordsSqlWritesAsKeywords(string $word, bool $expected): void
    {
        self::assertSame($expected, (new SqlHighlighter())->isKeyword($word));
    }

    public function testHoleSaysWhereTheValueThatFillsItComesFrom(): void
    {
        self::assertSame(
            '<span class="hole tone-warn" title="This is a gap: an object property fills it.">{$}</span>',
            (new SqlHighlighter())->hole(new StatementPart('', true, 'property', 'an object property')),
        );
    }

    /**
     * @return list<array{string|null, string|null, string}>
     */
    public static function providerHoleLabel(): array
    {
        return [
            ['$sql', null, '{$sql}'],
            ['$query_2', null, '{$query_2}'],
            ['$クエリ', null, '{$クエリ}'],
            ['buildSql()', '$sql', '{$sql}'],
            [null, null, '{$}'],
            ['buildSql($sql)', null, '{$}'],
            ['$$name', null, '{$}'],
            ['$sql + $other', null, '{$}'],
            ['$sql<script>', null, '{$}'],
        ];
    }

    #[DataProvider('providerHoleLabel')]
    public function testHoleLabelUsesOnlyAnIdentifiedVariableName(?string $expression, ?string $variable, string $expected): void
    {
        $gap = new StatementPart('', true, 'call', 'a call result', $expression, $variable);
        $highlighter = new SqlHighlighter();

        self::assertSame($expected, $highlighter->holeLabel($gap));
        self::assertStringEndsWith('>' . $expected . '</span>', $highlighter->hole($gap));
        self::assertStringNotContainsString('<script>', $highlighter->hole($gap));
    }

    /**
     * @return list<array{Origin, string}>
     */
    public static function providerHoleRole(): array
    {
        return [
            [Origin::External, 'tone-danger'],
            [Origin::Unreached, 'tone-neutral'],
            [Origin::Budget, 'tone-warn'],
            [Origin::Parameter, 'tone-warn'],
        ];
    }

    #[DataProvider('providerHoleRole')]
    public function testHoleRoleTintsAGapByWhereItCameFrom(Origin $origin, string $expected): void
    {
        self::assertSame($expected, (new SqlHighlighter())->holeRole($origin->value));
    }
    public function testPlainUsesNamedGapsWithoutEscapingKnownText(): void
    {
        self::assertSame('SELECT * FROM {$table} WHERE id < 3 AND {$}', (new SqlHighlighter())->plain([
            new StatementPart('SELECT * FROM '),
            new StatementPart('', true, 'parameter', 'a parameter', '$table'),
            new StatementPart(' WHERE id < 3 AND '),
            new StatementPart('', true, 'call', 'a call result', 'condition()'),
        ]));
    }

}
