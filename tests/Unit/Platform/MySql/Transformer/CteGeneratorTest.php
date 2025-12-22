<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Transformer;

use ZtdQuery\Platform\MySql\Transformer\CteGenerator;
use PHPUnit\Framework\TestCase;

final class CteGeneratorTest extends TestCase
{
    public function testGenerateWithColumnsAndRows(): void
    {
        $generator = new CteGenerator();
        $rows = [
            ['id' => 1, 'name' => 'Alice'],
        ];

        $cte = $generator->generate('users', $rows, ['id', 'name']);

        $this->assertStringContainsString('`users` AS (SELECT CAST(1 AS SIGNED) AS `id`, CAST(\'Alice\' AS CHAR) AS `name`)', $cte);
    }

    public function testGenerateWithColumnsAndNoRowsUsesEmptySelect(): void
    {
        $generator = new CteGenerator();

        $cte = $generator->generate('users', [], ['id', 'name']);

        $this->assertStringContainsString('SELECT CAST(NULL AS CHAR) AS `id`, CAST(NULL AS CHAR) AS `name` FROM DUAL WHERE 0', $cte);
    }

    public function testGenerateWithColumnsAndNoRowsUsesMysqlTypes(): void
    {
        $generator = new CteGenerator();
        $columnTypes = [
            'id' => 'INT',
            'total' => 'DECIMAL(10,2)',
        ];

        $cte = $generator->generate('orders', [], ['id', 'total'], $columnTypes);

        $this->assertStringContainsString('SELECT CAST(NULL AS SIGNED) AS `id`, CAST(NULL AS DECIMAL(10,2)) AS `total` FROM DUAL WHERE 0', $cte);
    }

    public function testGenerateWithoutColumnsAndNoRowsThrows(): void
    {
        $generator = new CteGenerator();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Cannot shadow table 'users' with empty data");

        $generator->generate('users', []);
    }

    public function testFormatSupportsNullBoolFloatAndStringable(): void
    {
        $generator = new CteGenerator();
        $rows = [
            ['flag' => true, 'count' => 2.5, 'name' => new StringableValue('Alice'), 'note' => null],
        ];

        $cte = $generator->generate('values', $rows, ['flag', 'count', 'name', 'note']);

        $this->assertStringContainsString('TRUE AS `flag`', $cte);
        $this->assertStringContainsString('2.5 AS `count`', $cte);
        $this->assertStringContainsString('Alice AS `name`', $cte);
        $this->assertStringContainsString('NULL AS `note`', $cte);
    }

    public function testGenerateWithDecimalColumnType(): void
    {
        $generator = new CteGenerator();
        $rows = [
            ['id' => 1, 'total' => '99.99'],
            ['id' => 2, 'total' => '150.50'],
        ];
        $columnTypes = [
            'id' => 'INT',
            'total' => 'DECIMAL(10,2)',
        ];

        $cte = $generator->generate('orders', $rows, ['id', 'total'], $columnTypes);

        // Values should be cast to their MySQL types, not PHP types
        $this->assertStringContainsString("CAST('1' AS SIGNED) AS `id`", $cte);
        $this->assertStringContainsString("CAST('99.99' AS DECIMAL(10,2)) AS `total`", $cte);
        $this->assertStringContainsString("CAST('150.50' AS DECIMAL(10,2)) AS `total`", $cte);
    }

    public function testGenerateWithVariousColumnTypes(): void
    {
        $generator = new CteGenerator();
        $rows = [
            [
                'id' => 1,
                'price' => '10.5',
                'created' => '2024-01-01 12:00:00',
                'active_date' => '2024-01-01',
            ],
        ];
        $columnTypes = [
            'id' => 'BIGINT',
            'price' => 'DOUBLE',
            'created' => 'DATETIME',
            'active_date' => 'DATE',
        ];

        $cte = $generator->generate('items', $rows, ['id', 'price', 'created', 'active_date'], $columnTypes);

        $this->assertStringContainsString("CAST('1' AS SIGNED) AS `id`", $cte);
        $this->assertStringContainsString("CAST('10.5' AS DOUBLE) AS `price`", $cte);
        $this->assertStringContainsString("CAST('2024-01-01 12:00:00' AS DATETIME) AS `created`", $cte);
        $this->assertStringContainsString("CAST('2024-01-01' AS DATE) AS `active_date`", $cte);
    }

    public function testFormatRejectsUnsupportedObject(): void
    {
        $generator = new CteGenerator();
        $rows = [
            ['col' => new NonStringableValue()],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported value type');

        $generator->generate('values', $rows, ['col']);
    }
}

final class StringableValue
{
    /**
     * String content returned by __toString().
     *
     * @var string
     */
    private string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

final class NonStringableValue
{
}
