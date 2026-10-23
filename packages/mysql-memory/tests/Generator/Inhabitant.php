<?php

declare(strict_types=1);

namespace Tests\Generator;

/**
 * Samples a semantic type whose cross-field invariants require coordinated arguments.
 */
interface Inhabitant
{
    /**
     * Constructs one value without consulting SQL execution or filtering its outcome.
     */
    public function sample(Choices $choices): object;
}
