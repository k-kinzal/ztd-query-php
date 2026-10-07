<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Leaf;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * `@name := value`: assigns a user variable and answers the value.
 *
 * @visibility MySqlMemory
 */
final class Assignment implements Evaluable
{
    /**
     * @param string $name The variable name
     * @param Evaluable $value The value assigned
     * @param Domain $stored The domain the variable holds the value in
     */
    public function __construct(public readonly string $name, public readonly Evaluable $value, public readonly Domain $stored)
    {
    }

    /**
     * Answers the domain of the value.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->value->domain();
    }

    /**
     * Evaluates the value and assigns it.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        $value = $this->value->evaluate($frame);
        $frame->context->variables->assign($this->name, $value, $this->stored);

        return $value;
    }
}
