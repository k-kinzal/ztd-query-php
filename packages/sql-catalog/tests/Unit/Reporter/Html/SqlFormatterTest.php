<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\StatementPart;
use SqlCatalog\Reporter\Html\SqlFormatter;
use SqlFormatter\Core\FormattingException;

#[CoversClass(SqlFormatter::class)]
#[UsesClass(StatementPart::class)]
final class SqlFormatterTest extends TestCase
{
    /**
     * @return list<array{string, string}>
     */
    public static function providerFormat(): array
    {
        return [
            ['SELECT 1', "SELECT\n    1"],
            ['SELECT id,name FROM users WHERE active=1 AND age>=18 ORDER BY name', "SELECT\n    id,\n    name\nFROM\n    users\nWHERE\n    active = 1\n    AND age >= 18\nORDER BY\n    name"],
            ['UPDATE t SET a = 1, b = 2 WHERE id = 1', "UPDATE\n    t\nSET\n    a = 1,\n    b = 2\nWHERE\n    id = 1"],
            ['SELECT * FROM users WHERE id = :id', "SELECT\n    *\nFROM\n    users\nWHERE\n    id = :id"],
            ['SELECT DISTINCT ON (id) id FROM users WHERE id=$1', "SELECT DISTINCT\n    ON (id) id\nFROM\n    users\nWHERE\n    id = $1"],
            ['INSERT OR IGNORE INTO t (id) VALUES (?)', "INSERT\n    OR IGNORE\nINTO\n    t (id)\nVALUES\n    (?)"],
        ];
    }

    #[DataProvider('providerFormat')]
    public function testFormatExpandsSqlAcrossSupportedDialects(string $sql, string $expected): void
    {
        $formatter = new SqlFormatter();
        $parts = $formatter->format([new StatementPart($sql)]);

        self::assertSame($expected, implode('', array_map(static fn (StatementPart $part): string => $part->text, $parts)));
        self::assertEquals($parts, $formatter->format($parts));
    }

    public function testFormatPreservesGapIdentityAndMetadata(): void
    {
        $table = new StatementPart('', true, 'parameter', 'a parameter', '$table');
        $id = new StatementPart('', true, 'call', 'a call result', 'readId()', '$id');
        $parts = (new SqlFormatter())->format([new StatementPart('SELECT * FROM '), $table, new StatementPart(' WHERE id = '), $id]);

        self::assertSame("SELECT\n    *\nFROM\n    ", $parts[0]->text);
        self::assertSame($table, $parts[1]);
        self::assertSame("\nWHERE\n    id = ", $parts[2]->text);
        self::assertSame($id, $parts[3]);
        self::assertCount(4, $parts);
    }

    public function testMaskPreservesLiteralMarkersAndRestoresGapsWithoutCollisions(): void
    {
        $gap = new StatementPart('', true, 'external', 'external input', '$value');
        $parts = (new SqlFormatter())->format([
            new StatementPart("SELECT '__sql_catalog_gap_0__', '{\$}', 'prefix"),
            $gap,
            new StatementPart("suffix' FROM `table_"),
            $gap,
            new StatementPart('`'),
        ]);

        self::assertSame("SELECT\n    '__sql_catalog_gap_0__',\n    '{\$}',\n    'prefix", $parts[0]->text);
        self::assertSame($gap, $parts[1]);
        self::assertSame("suffix'\nFROM\n    `table_", $parts[2]->text);
        self::assertSame($gap, $parts[3]);
        self::assertSame('`', $parts[4]->text);
    }

    public function testFormatPreservesLiteralWhitespaceAndLineCommentBoundaries(): void
    {
        $parts = (new SqlFormatter())->format([new StatementPart("SELECT 'a  b', 1 -- keep this\nFROM users WHERE id = ?")]);

        self::assertStringContainsString("'a  b'", $parts[0]->text);
        self::assertStringContainsString("-- keep this\n", $parts[0]->text);
        self::assertStringContainsString("WHERE\n    id = ?", $parts[0]->text);
    }

    public function testFormatLeavesUnsupportedAndUnknownStatementsIntact(): void
    {
        $formatter = new SqlFormatter();
        $gap = new StatementPart('', true, 'call', 'a call result', 'sql()');
        $invalid = [new StatementPart("SELECT (\n "), $gap];

        self::assertSame($invalid, $formatter->format($invalid));
        self::assertSame([$gap], $formatter->format([$gap]));
        self::assertSame([], $formatter->format([]));
        self::assertSame('', $formatter->format([new StatementPart('')])[0]->text);
    }

    public function testMaskParametersPreservesClientPlaceholdersAndPostgresCasts(): void
    {
        $formatter = new SqlFormatter();
        $sql = "SELECT :id::integer, '%s', :id FROM users WHERE name = %s";
        [$masked, $replacements] = $formatter->maskParameters($sql, [], '__test_');

        self::assertSame("SELECT __test_0__::integer, '%s', __test_1__ FROM users WHERE name = __test_2__", $masked);
        self::assertSame($sql, implode('', array_map(static fn (StatementPart $part): string => $part->text, $formatter->restore($masked, $replacements))));
        self::assertStringContainsString("FROM\n    users\nWHERE\n    name = %s", implode('', array_map(static fn (StatementPart $part): string => $part->text, $formatter->format([new StatementPart($sql)]))));
    }

    public function testRestoreRejectsLostGaps(): void
    {
        $this->expectException(FormattingException::class);

        (new SqlFormatter())->restore('SELECT 1', ['missing' => new StatementPart('', true)]);
    }
}
