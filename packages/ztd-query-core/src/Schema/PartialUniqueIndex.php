<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

use ZtdQuery\Exception\InvalidDefinitionException;

/**
 * A unique index whose candidate rows are restricted by a SQL predicate.
 */
final class PartialUniqueIndex
{
    /**
     * @var non-empty-list<string>
     */
    public readonly array $columns;

    /**
     * @param list<string> $columns
     */
    public function __construct(
        public readonly string $name,
        array $columns,
        public readonly string $predicate,
    ) {
        if (trim($name) === '' || $columns === [] || trim($predicate) === '') {
            throw new InvalidDefinitionException('Partial unique indexes require a name, columns, and predicate.');
        }
        $this->columns = $columns;
    }
}
