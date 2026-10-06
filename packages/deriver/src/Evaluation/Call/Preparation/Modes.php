<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call\Preparation;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;

/**
 * Matches argument positions and names to a captured reference signature.
 * @visibility root
 */
final class Modes
{
    /**
     * @param Machine $machine Shared argument expansion semantics
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Counts preceding positional actuals, including known argument unpacking.
     * @param Instruction $instruction Argument with preceding actual descriptions
     * @param State $state Already evaluated argument registers
     * @return int|null Next position, or unknown when an earlier unpack is unresolved
     */
    public function position(Instruction $instruction, State $state): ?int
    {
        $position = 0;
        foreach ((new ArgumentBinding($this->machine))->actuals($instruction, $state) as $argument) {
            if ($argument->name === '*') {
                return null;
            }
            if ($argument->name === null) {
                $position++;
            }
        }
        return $position;
    }

    /**
     * Selects reference or value passing without treating unknown signatures as value-only.
     * @param Target $target Captured signature
     * @param int|null $position Positional index
     * @param string|null $name Named argument spelling
     * @return bool|null Reference mode, value mode, or an unresolved choice
     */
    public function select(Target $target, ?int $position, ?string $name): ?bool
    {
        if ($target->signature === null) {
            return null;
        }
        $variadic = false;
        foreach ($target->signature->parameters as $index => $parameter) {
            if ($name === $parameter->name || $name === null && $position === $index) {
                return $parameter->byReference;
            }
            if ($parameter->variadic) {
                $variadic = $parameter->byReference;
            }
        }
        return $name === null && $position === null ? null : $variadic;
    }
}
