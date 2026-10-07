<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Leaf;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Variable\Scope;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Definition;

/**
 * A system variable read: `@@name`, `@@SESSION.name` or `@@GLOBAL.name`.
 *
 * @visibility MySqlMemory
 */
final class SystemVariableRead implements Evaluable
{
    /**
     * @param Definition $definition The variable
     * @param Scope $scope The scope read
     * @param Domain $domain The domain of the value
     */
    public function __construct(public readonly Definition $definition, public readonly Scope $scope, public readonly Domain $domain)
    {
    }

    /**
     * Answers the domain of the value.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Reads the value of the variable now.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        $value = $frame->context->variables->system($this->definition, $this->scope);
        if ($value === null) {
            return null;
        }
        if ($this->domain->kind === Kind::Integer) {
            return is_int($value) ? $value : (in_array(strtoupper((string) $value), ['ON', 'TRUE'], true) ? 1 : (int) $value);
        }
        if ($this->domain->kind === Kind::Double) {
            return (float) $value;
        }

        return (string) $value;
    }
}
