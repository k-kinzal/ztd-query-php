<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

/**
 * A dialect-neutral projection of mutation rows returned to the client.
 */
final class ReturningProjection
{
    /**
     * @param list<array{source: string|null, output: string|null}> $items
     */
    private function __construct(private readonly array $items)
    {
    }

    /**
     * @param list<array{source: string|null, output: string|null}> $items
     */
    public static function fromItems(array $items): self
    {
        if ($items === []) {
            throw new \InvalidArgumentException('Returning projection requires at least one item.');
        }
        foreach ($items as $item) {
            if ($item['source'] === null && $item['output'] !== null) {
                throw new \InvalidArgumentException('Wildcard returning projections cannot have an output name.');
            }
            if ($item['source'] === '' || $item['output'] === '') {
                throw new \InvalidArgumentException('Returning projection names must not be empty.');
            }
        }

        return new self($items);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public function project(array $rows): array
    {
        $projectedRows = [];
        foreach ($rows as $row) {
            $projected = [];
            foreach ($this->items as $item) {
                if ($item['source'] === null) {
                    $projected = array_merge($projected, $row);
                    continue;
                }
                $output = $item['output'] ?? $item['source'];
                $projected[$output] = $row[$item['source']] ?? null;
            }
            $projectedRows[] = $projected;
        }

        return $projectedRows;
    }

    /** @return list<array{source: string|null, output: string|null}> */
    public function items(): array
    {
        return $this->items;
    }
}
