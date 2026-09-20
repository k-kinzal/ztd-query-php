<?php

declare(strict_types=1);

namespace Fuzz\Shared\Input;

/**
 * Generates the catalog and fixture values independently of platform parsing.
 */
final class Schema
{
    /**
     * @var non-empty-list<string>
     */
    public readonly array $tables;
    /**
     * @var non-empty-list<string>
     */
    public readonly array $numbers;
    /**
     * Initial rows per table, including empty fixtures.
     */
    public readonly int $rowCount;

    /**
     * Varies the initial numeric values independently of catalog shape.
     */
    public readonly int $valueOffset;

    /**
     * Decode table count, numeric column count and initial row count.
     */
    public function __construct(string $bytes)
    {
        $input = new Bytes($bytes);
        $this->tables = array_map(static fn (int $index): string => 'fuzz_' . $index, range(0, $input->next(3)));
        $this->numbers = array_map(static fn (int $index): string => 'v' . $index, range(0, $input->next(3)));
        $this->rowCount = $input->next(5);
        $this->valueOffset = $input->next(17) - 8;
    }

    /**
     * Build the portable CREATE TABLE statement for the generated columns.
     */
    public function create(string $table): string
    {
        $columns = array_map(static fn (string $name): string => "$name INTEGER NOT NULL DEFAULT 0", $this->numbers);
        return "CREATE TABLE $table (id INTEGER PRIMARY KEY, " . implode(', ', $columns) . ', label VARCHAR(100))';
    }

    /**
     * Render one complete row in schema column order.
     */
    public function values(int $id, int $value, ?string $label): string
    {
        $values = [(string) $id];
        foreach (array_keys($this->numbers) as $index) {
            $values[] = (string) ($value + $index);
        }
        $values[] = self::literal($label);
        return '(' . implode(', ', $values) . ')';
    }

    /**
     * Quote a portable string literal or SQL NULL.
     */
    public static function literal(?string $value): string
    {
        return $value === null ? 'NULL' : "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * Choose a fixture including null, Unicode and punctuation.
     */
    public static function label(int $choice): ?string
    {
        return ['plain', "O'Brien", "\u{2603}", '', null, 'SELECT /* ; */', '0'][$choice % 7];
    }
}
