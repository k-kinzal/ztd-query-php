<?php

declare(strict_types=1);

namespace MySqlMemory\Iterator\Transform;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Plan\Path\Transform\Sort;
use MySqlMemory\Value\Json\JsonKind;
use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Order;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Reads the whole input and answers its rows in the order of the sort keys; ties keep input order.
 *
 * @visibility MySqlMemory
 */
final class SortIterator implements RowIterator
{
    private Frame $frame;

    /**
     * @var list<list<int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null>>
     */
    private array $rows = [];

    /**
     * @var list<array<int, array{\MySqlMemory\Plan\Path\Source\TableScan, list<int|float|string|null>}>>
     */
    private array $records = [];

    private int $next = 0;

    /**
     * @param Sort $path The path executed
     * @param RowIterator $input The iterator of the input
     */
    public function __construct(public readonly Sort $path, public readonly RowIterator $input)
    {
    }

    /**
     * Reads and sorts the input.
     */
    #[Override]
    public function init(Frame $frame): void
    {
        $this->frame = $frame;
        $this->input->init($frame);
        $rows = [];
        $records = [];
        $position = $frame->context->row;
        try {
            while (($row = $this->input->read()) !== null) {
                $rows[] = $row;
                $records[] = $frame->scanned;
                if ($frame->outer === null) {
                    $frame->context->row = count($rows) + 1;
                }
            }
        } finally {
            $frame->context->row = $position;
        }
        $keys = $this->path->keys;
        $this->nonScalar($rows, $frame);
        $indexes = array_keys($rows);
        usort($indexes, static function (int $left, int $right) use ($rows, $keys): int {
            foreach ($keys as [$position, $domain, $descending]) {
                $order = Order::compare(\MySqlMemory\Evaluation\ExactDecimal::raw($rows[$left][$position]), \MySqlMemory\Evaluation\ExactDecimal::raw($rows[$right][$position]), $domain);
                if ($order !== 0) {
                    return $descending ? -$order : $order;
                }
            }

            return $left <=> $right;
        });
        $this->rows = array_map(static fn (int $index): array => $rows[$index], $indexes);
        $this->records = array_map(static fn (int $index): array => $records[$index], $indexes);
        $this->next = 0;
    }

    /**
     * Warns once that sorting by an array or an object of a JSON key is not supported, as the server does when it meets one (verified on a live 8.4 server).
     *
     * @param list<list<int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null>> $rows
     */
    public function nonScalar(array $rows, Frame $frame): void
    {
        foreach ($this->path->keys as [$position, $domain]) {
            if ($domain->kind !== Kind::Json) {
                continue;
            }
            foreach ($rows as $row) {
                $value = $row[$position];
                if ($value !== null && in_array(JsonNode::load((string) $value)->type, [JsonKind::Array, JsonKind::Object], true)) {
                    $frame->context->diagnostics->warning(StatementError::NotSupportedYet, StatementError::NotSupportedYet->message('sorting of non-scalar JSON values'));

                    return;
                }
            }
        }
    }

    /**
     * Answers the next row in order.
     */
    #[Override]
    public function read(): ?array
    {
        $row = $this->rows[$this->next++] ?? null;
        if ($row !== null) {
            $this->frame->buffered = $row;
            $this->frame->scanned = $this->records[$this->next - 1];
            $variables = $this->frame->context->variables;
            foreach ($this->frame->scanned as [$scan, $fields]) {
                $variables->instance->dictionary->cache->buffers->retain($variables, $scan->table, $fields, $scan->handle);
            }
        }

        return $row;
    }
}
