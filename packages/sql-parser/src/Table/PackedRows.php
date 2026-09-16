<?php

declare(strict_types=1);

namespace SqlParser\Table;

/**
 * Action rows kept packed in binary and decoded one state at a time.
 *
 * A full SQL grammar has hundreds of thousands of actions, while a parse
 * visits a few hundred states, so rows are unpacked on first use and
 * remembered.
 *
 * @visibility root
 */
final class PackedRows implements ActionRows
{
    /**
     * @var array<int, array<int, int>>
     */
    private array $decoded = [];

    /**
     * @param string $blob Every row, packed as the codec writes them
     * @param list<int> $offsets Byte offset of each state's row, with one more for the end
     */
    public function __construct(
        private readonly string $blob,
        private readonly array $offsets,
    ) {
    }

    /**
     * Answers the explicit actions of one state.
     *
     * @param int $state State number
     *
     * @return array<int, int> Action code by symbol number
     */
    public function row(int $state): array
    {
        if (isset($this->decoded[$state])) {
            return $this->decoded[$state];
        }
        $start = $this->offsets[$state] ?? null;
        $end = $this->offsets[$state + 1] ?? null;
        if ($start === null || $end === null || $end <= $start) {
            return $this->decoded[$state] = [];
        }
        $count = intdiv($end - $start, 6);
        $symbols = TableCodec::integers('v*', substr($this->blob, $start, $count * 2));
        $actions = TableCodec::integers('l*', substr($this->blob, $start + $count * 2, $count * 4));

        return $this->decoded[$state] = array_combine($symbols, $actions);
    }

    /**
     * Answers how many states there are.
     *
     * @return int State count
     */
    public function count(): int
    {
        return max(count($this->offsets) - 1, 0);
    }
}
