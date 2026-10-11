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
     * @param Domain $domain The assignment expression's resolved result domain
     */
    public function __construct(public readonly string $name, public readonly Evaluable $value, public readonly Domain $stored, public readonly Domain $domain)
    {
    }

    /**
     * Answers the resolved domain of the assignment result.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Evaluates the value and assigns it.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        $value = $this->value->evaluate($frame);
        if ($this->value->domain()->kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Bit) {
            $value = \MySqlMemory\Evaluation\Convert::toInteger($value, $this->value->domain(), $frame->context, true);
        }
        $frame->context->variables->assign($this->name, $value, $this->stored);

        return $value;
    }
}
