<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Source;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Family\Jsons;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Plan\Path\Source\JsonColumn;
use MySqlMemory\Plan\Path\Source\JsonTableScan;
use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Json\JsonPath;
use Override;

/**
 * Computes the rows of JSON_TABLE from its document.
 *
 * A NULL document has no rows. For each value a path selects, the columns of its level are
 * computed and the counter counts from 1; then the rows of each nested path follow one nested
 * path at a time, the columns of the other nested paths NULL, and a value whose nested paths
 * select nothing is one row with all of them NULL (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-table-functions.html.
 *
 * @visibility MySqlMemory
 */
final class JsonTableIterator implements RowIterator
{
    /**
     * @var list<list<int|float|string|null>>
     */
    private array $rows = [];

    private int $next = 0;

    /**
     * @param JsonTableScan $path The path executed
     */
    public function __construct(public readonly JsonTableScan $path)
    {
    }

    /**
     * Reads the document and computes the rows.
     */
    #[Override]
    public function init(Frame $frame): void
    {
        $document = Jsons::read($this->path->document, $frame, 1, 'json_table');
        $this->rows = $document === null ? [] : self::rows($document, $this->path->path, $this->path->columns, $frame->context);
        $this->next = 0;
    }

    /**
     * Computes the rows a path and its columns make of a value.
     *
     * @param list<JsonColumn> $columns
     * @return list<list<int|float|string|null>>
     *
     * @throws SqlError When a column raises its error
     */
    public static function rows(JsonNode $value, JsonPath $path, array $columns, Context $context): array
    {
        $rows = [];
        foreach ($path->select($value) as $index => $selected) {
            $base = [];
            $nested = [];
            foreach ($columns as $column) {
                if ($column->kind === 'nested') {
                    $nested[] = [count($base), $column];
                    array_push($base, ...array_fill(0, $column->width(), null));
                    continue;
                }
                $base[] = $column->kind === 'ordinality' ? $index + 1 : $column->value($selected, $context);
            }
            $added = false;
            foreach ($nested as [$offset, $column]) {
                foreach (self::rows($selected, $column->path ?? new JsonPath([]), $column->columns, $context) as $inner) {
                    $row = $base;
                    array_splice($row, $offset, count($inner), $inner);
                    $rows[] = $row;
                    $added = true;
                }
            }
            if (!$added) {
                $rows[] = $base;
            }
        }

        return $rows;
    }

    /**
     * Answers the next row.
     */
    #[Override]
    public function read(): ?array
    {
        return $this->rows[$this->next++] ?? null;
    }
}
