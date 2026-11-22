<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement;

/**
 * A statement or query that reads rows from an input relation structure.
 *
 * @visibility SqlSemantics
 */
interface Selection extends Node
{
    /**
     * Answers the input relation structure, or null when the selection has no input.
     */
    public function input(): ?Relation;
}
