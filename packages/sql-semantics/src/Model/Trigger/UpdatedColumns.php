<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Trigger;

use Override;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * An update trigger invoked only when specified columns are assigned.
 * @visibility public
 */
final class UpdatedColumns implements Event
{
    /**
     * @var non-empty-list<string> Validated ordered operands
     */
    public readonly array $columns;

    /**
     * @param list<string> $columns
     * @throws InvalidStructure
     */
    public function __construct(array $columns)
    {
        Collections::strings($columns);
        if ($columns === []) {
            throw new InvalidStructure('UPDATE OF requires at least one column.');
        }
        $this->columns = Collections::nonEmpty($columns);
    }

    #[Override]
    public function operation(): WriteEvent
    {
        return WriteEvent::Update;
    }
}
