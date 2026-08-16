<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlTypeSemantics;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(MySqlTypeSemantics::class)]
final class MySqlTypeSemanticsTest extends TestCase
{
    public function testRewritesEnumOrderingAndOrderedComparisonsToRanks(): void
    {
        $semantics = new MySqlTypeSemantics();
        $tables = [
            'items' => [
                'rows' => [],
                'columns' => ['size'],
                'columnTypes' => ['size' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('small','medium','large')")],
            ],
        ];

        self::assertSame(
            "SELECT * FROM items WHERE FIELD(size, 'small', 'medium', 'large') > FIELD('small', 'small', 'medium', 'large') ORDER BY FIELD(size, 'small', 'medium', 'large') DESC",
            $semantics->rewrite("SELECT * FROM items WHERE size > 'small' ORDER BY size DESC", $tables),
        );
    }

    public function testRewritesQualifiedAndQuotedEnumColumns(): void
    {
        $semantics = new MySqlTypeSemantics();
        $tables = [
            'items' => [
                'rows' => [],
                'columns' => ['size'],
                'columnTypes' => ['size' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('small','large')")],
            ],
        ];

        self::assertSame(
            "SELECT * FROM items WHERE FIELD(`items`.`size`, 'small', 'large') <= FIELD('large', 'small', 'large') ORDER BY FIELD(`items`.`size`, 'small', 'large')",
            $semantics->rewrite("SELECT * FROM items WHERE `items`.`size` <= 'large' ORDER BY `items`.`size`", $tables),
        );
    }

    public function testLeavesPlainStringsAndComplexOrderExpressionsUntouched(): void
    {
        $semantics = new MySqlTypeSemantics();
        $tables = [
            'items' => [
                'rows' => [],
                'columns' => ['name', 'size'],
                'columnTypes' => [
                    'name' => new ColumnType(ColumnTypeFamily::STRING, 'VARCHAR(255)'),
                    'size' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('small','large')"),
                ],
            ],
        ];
        $sql = "SELECT * FROM items WHERE name > 'a' ORDER BY COALESCE(size, 'small')";

        self::assertSame($sql, $semantics->rewrite($sql, $tables));
    }
}
