<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Conflict;

/**
 * A conflict selected by the declared constraint name.
 * @visibility public
 */
final class ConstraintConflict implements Target
{
    /**
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly string $name)
    {
        if ($name === '') {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A constraint selector requires its name.');
        }
    }
}
