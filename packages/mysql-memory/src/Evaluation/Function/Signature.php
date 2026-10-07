<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function;

use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Leaf\Retyped;
use MySqlMemory\Evaluation\Compile\Settings;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Typing\Domain;

/**
 * What resolving the domain of a call reads: the arguments, and the session settings.
 *
 * @visibility MySqlMemory
 */
final class Signature
{
    /**
     * @param list<Evaluable> $arguments The compiled arguments
     * @param Settings $settings The session settings
     */
    public function __construct(public readonly array $arguments, public readonly Settings $settings)
    {
    }

    /**
     * Answers the domains of the arguments.
     *
     * @return list<Domain>
     */
    public function domains(): array
    {
        return array_map(static fn (Evaluable $argument): Domain => $argument->domain(), $this->arguments);
    }

    /**
     * Tells whether any argument can be NULL.
     */
    public function nullable(): bool
    {
        foreach ($this->arguments as $argument) {
            if ($argument->domain()->nullable) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the constant value of an argument, or null when it is not a constant.
     */
    public function constant(int $index): int|float|string|null
    {
        $argument = $this->arguments[$index] ?? null;

        while ($argument instanceof Retyped) {
            $argument = $argument->evaluable;
        }

        return $argument instanceof Constant ? $argument->value : null;
    }
}
