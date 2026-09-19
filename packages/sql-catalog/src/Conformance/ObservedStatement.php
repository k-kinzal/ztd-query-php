<?php

declare(strict_types=1);

namespace SqlCatalog\Conformance;

/**
 * One statement a program actually sent to a database.
 *
 * @visibility root
 */
final class ObservedStatement
{
    /**
     * @param string $sql The statement text as the driver received it
     * @param list<string|int|float|bool|null> $positional The values bound by position, in order
     * @param array<string, string|int|float|bool|null> $named The values bound by name
     * @param string $source Where the statement came from, such as the file that issued it
     */
    public function __construct(
        public readonly string $sql,
        public readonly array $positional = [],
        public readonly array $named = [],
        public readonly string $source = '',
    ) {
    }

    /**
     * The statement with its whitespace normalized, so formatting does not matter.
     */
    public function normalized(): string
    {
        return trim(preg_replace('/\s+/', ' ', $this->sql) ?? $this->sql);
    }
}
