<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\StatementPart;
use SqlCatalog\Reporter\Html\SqlFormatter;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(SqlFormatter::class)]
#[UsesClass(StatementPart::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class SqlFormatterTest extends TestCase
{
    /**
     * @return list<array{string, string}>
     */
    public static function providerFormat(): array
    {
        return [
            ['SELECT 1', 'SELECT 1'],
            ['SELECT id FROM users WHERE a = 1 AND b = 2 OR c = 3', "SELECT id\nFROM users\nWHERE a = 1\n  AND b = 2\n  OR c = 3"],
            ['SELECT * FROM a LEFT OUTER JOIN b ON a.id = b.a_id JOIN c ON c.id = b.c_id', "SELECT *\nFROM a\nLEFT OUTER JOIN b ON a.id = b.a_id\nJOIN c ON c.id = b.c_id"],
            ['SELECT * FROM t WHERE x BETWEEN 1 AND 5 AND y IN (SELECT id FROM u WHERE z = 1)', "SELECT *\nFROM t\nWHERE x BETWEEN 1 AND 5\n  AND y IN (\n    SELECT id\n    FROM u\n    WHERE z = 1\n  )"],
            ['DELETE FROM t WHERE id = ?', "DELETE FROM t\nWHERE id = ?"],
            ['UPDATE t SET a = 1, b = 2 WHERE id = 1', "UPDATE t\nSET a = 1,\n  b = 2\nWHERE id = 1"],
            ['INSERT INTO t (a) VALUES (1),(2) ON DUPLICATE KEY UPDATE a = VALUES(a)', "INSERT INTO t (a)\nVALUES (1),\n  (2)\nON DUPLICATE KEY UPDATE a = VALUES(a)"],
            ['CREATE TABLE t (id INT, name TEXT)', "CREATE TABLE t (\n  id INT,\n  name TEXT\n)"],
            ['SELECT CASE WHEN a = 1 THEN 2 ELSE 3 END FROM t', "SELECT CASE\n    WHEN a = 1 THEN 2\n    ELSE 3\n  END\nFROM t"],
            ['SELECT a FROM t UNION ALL SELECT b FROM u ORDER BY 1 LIMIT 5', "SELECT a\nFROM t\nUNION ALL\nSELECT b\nFROM u\nORDER BY 1\nLIMIT 5"],
            ["SELECT 'from where' AS s, count(*) FROM t GROUP BY s HAVING s > 1", "SELECT 'from where' AS s, count(*)\nFROM t\nGROUP BY s\nHAVING s > 1"],
        ];
    }

    #[DataProvider('providerFormat')]
    public function testFormatLaysTheStatementOutAClausePerLine(string $sql, string $expected): void
    {
        $parts = (new SqlFormatter())->format(StatementPart::of(TextPattern::fromText($sql)));

        self::assertSame($expected, implode('', array_map(static fn (StatementPart $part): string => $part->text, $parts)));
    }

    public function testFormatKeepsTheGapsWhereTheyWere(): void
    {
        $pattern = TextPattern::fromSegments([
            new LiteralText('DELETE FROM '),
            new TextHole(Origin::Property, TypeShape::unknown()),
            new LiteralText("\n\t\tWHERE meta_key LIKE %s\n\t\tOR meta_key = 'a'"),
        ]);
        $parts = (new SqlFormatter())->format(StatementPart::of($pattern));

        self::assertSame(
            ['DELETE FROM ', true, "\nWHERE meta_key LIKE %s\n  OR meta_key = 'a'"],
            [$parts[0]->text, $parts[1]->isGap, $parts[2]->text],
        );
    }

    public function testFormatChangesOnlyWhitespace(): void
    {
        $sql = "select  a,b\n from t where a='x  y' and b=\"p  q\" -- note\n order by a";
        $parts = (new SqlFormatter())->format(StatementPart::of(TextPattern::fromText($sql)));
        $formatted = implode('', array_map(static fn (StatementPart $part): string => $part->text, $parts));

        self::assertSame((string) preg_replace('/\s+/', '', $sql), (string) preg_replace('/\s+/', '', $formatted));
        self::assertStringContainsString("'x  y'", $formatted);
    }

    public function testTokenizeCarriesAGapAsATokenOfItsOwn(): void
    {
        $parts = StatementPart::of(TextPattern::fromSegments([
            new LiteralText("SELECT 'a' "),
            new TextHole(Origin::Property, TypeShape::unknown()),
        ]));

        self::assertSame(
            ['word', 'ws', 'str', 'ws', 'gap'],
            array_column((new SqlFormatter())->tokenize($parts), 'kind'),
        );
    }

    public function testIndentBeforeNeverBreaksBeforeTheFirstToken(): void
    {
        $formatter = new SqlFormatter();
        $tokens = $formatter->tokenize(StatementPart::of(TextPattern::fromText('FROM t')));

        self::assertNull($formatter->indentBefore($tokens, 0));
    }

    public function testSymbolOpensABlockForASubquery(): void
    {
        $formatter = new SqlFormatter();

        self::assertNull($formatter->symbol('(', 'SELECT'));
        self::assertSame(0, $formatter->symbol(')', null));
    }

    public function testSymbolLeavesAPlainParenthesisAlone(): void
    {
        $formatter = new SqlFormatter();

        self::assertNull($formatter->symbol('(', 'ID'));
        self::assertNull($formatter->symbol(')', null));
    }

    public function testListIndentIsNullOutsideAListWorthALine(): void
    {
        self::assertNull((new SqlFormatter())->listIndent());
    }

    public function testWordBreaksBeforeAConditionButNotInsideBetween(): void
    {
        $formatter = new SqlFormatter();
        $formatter->word('SELECT', null, null);

        self::assertSame(2, $formatter->word('AND', null, null));
        self::assertNull($formatter->word('BETWEEN', null, null));
        self::assertNull($formatter->word('AND', null, null));
        self::assertSame(2, $formatter->word('AND', null, null));
    }

    public function testClauseTellsAJoinPhraseFromItsWords(): void
    {
        $formatter = new SqlFormatter();

        self::assertTrue($formatter->clause('LEFT', null, 'JOIN'));
        self::assertFalse($formatter->clause('JOIN', 'LEFT', null));
        self::assertTrue($formatter->clause('JOIN', 'T', null));
        self::assertFalse($formatter->clause('OUTER', 'LEFT', 'JOIN'));
        self::assertFalse($formatter->clause('VALUES', '=', '('));
        self::assertTrue($formatter->clause('VALUES', ')', '('));
    }

    public function testWordAtSkipsWhitespaceAndReadsSymbolsAsThemselves(): void
    {
        $formatter = new SqlFormatter();
        $tokens = $formatter->tokenize(StatementPart::of(TextPattern::fromText("a  ,  'x' b")));

        self::assertSame(',', $formatter->wordAt($tokens, 0, 1));
        self::assertNull($formatter->wordAt($tokens, 2, 1));
        self::assertSame('B', $formatter->wordAt($tokens, 4, 1));
        self::assertNull($formatter->wordAt($tokens, 0, -1));
    }

    public function testAssembleJoinsAdjacentRunsAndKeepsGaps(): void
    {
        $gap = new StatementPart('', true, 'property', 'an object property');
        $parts = (new SqlFormatter())->assemble(['SELECT', ' ', $gap, ' FROM', ' t']);

        self::assertSame(['SELECT ', true, ' FROM t'], [$parts[0]->text, $parts[1]->isGap, $parts[2]->text]);
    }

    public function testFormatBreaksBeforeValuesAfterAGap(): void
    {
        $pattern = TextPattern::fromText('INSERT INTO ')
            ->concat(TextPattern::fromHole(new TextHole(Origin::Property, TypeShape::unknown())))
            ->concat(TextPattern::fromText(' VALUES (1)'));
        $parts = (new SqlFormatter())->format(StatementPart::of($pattern));

        self::assertSame(['INSERT INTO ', "\nVALUES (1)"], [$parts[0]->text, $parts[2]->text]);
    }

    public function testWordLeavesEndAloneOutsideACase(): void
    {
        $formatter = new SqlFormatter();

        self::assertNull($formatter->word('END', null, null));
        self::assertNull($formatter->word('WHEN', null, null));
    }

    public function testListIndentBreaksTheRowsOfAnInsertButNotTheColumnsOfASelect(): void
    {
        $insert = new SqlFormatter();
        $insert->word('INSERT', null, null);
        $select = new SqlFormatter();
        $select->word('SELECT', null, null);

        self::assertSame(2, $insert->listIndent());
        self::assertNull($select->listIndent());
    }
}
