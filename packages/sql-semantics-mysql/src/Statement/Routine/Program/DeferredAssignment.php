<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;

/**
 * A stored SET item whose assigned operand requires invocation-time preparation.
 *
 * Its target remains a named local/system variable, an explicit system target,
 * or a user variable. It is not an ordinary SetItem accepting a scalar value.
 *
 * @visibility public
 * @example Keeping deferred assignments out of ordinary SET lists
 *     $item = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredAssignment(new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name('x')), new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]));
 *     $item instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetItem // => false
 */
final class DeferredAssignment implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Name|SystemTarget|UserVariable $target The assigned name or explicit variable target
     * @param RowOperand|DeferredOperand $value The complete assigned operand
     * @param VariableScope|null $scope The leading scope keyword of a named assignment
     * @param Name|null $qualifier The component, cache or trigger row of a named assignment
     */
    public function __construct(public readonly Name|SystemTarget|UserVariable $target, public readonly RowOperand|DeferredOperand $value, public readonly ?VariableScope $scope = null, public readonly ?Name $qualifier = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input($target instanceof Name || ($scope === null && $qualifier === null), 'Only a named assignment carries a leading scope or qualifier.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Binds the target and dependencies without imposing the invocation's scalar-use check.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        $legacy = $derivation->context->profile->grammar === \SqlSemantics\Contract\GrammarRelease::MySql5651;
        if ($this->target instanceof SystemTarget) {
            $derivation->scalar($this->target, $scope->environment);
            if ($this->target->instance === null) {
                (new \SqlSemantics\Platform\MySql\Rules\Utility\VariableAccess())->find($this->target->name->value, $derivation, true, $legacy ? $this->target : $this->value);
            } else {
                $this->target->structured($derivation);
            }
        } elseif ($this->target instanceof UserVariable) {
            $derivation->scalar($this->target, $scope->environment);
        } elseif ($this->qualifier === null && $scope->complete && !$scope->variable($this->target)) {
            (new \SqlSemantics\Platform\MySql\Rules\Utility\VariableAccess())->find($this->target->value, $derivation, true, $legacy ? $this : $this->value, !$legacy);
        }
        $derivation->scalar($this->value, $scope->environment);
    }

    /**
     * Writes the target and retained operand as one SET item.
     */
    public function render(Output $out): void
    {
        if ($this->scope !== null) {
            $out->keyword($this->scope->value);
        }
        if ($this->qualifier !== null) {
            $out->name($this->qualifier, NameUse::Label)->symbol('.');
        }
        if ($this->target instanceof Name) {
            $out->name($this->target, NameUse::Label);
        } else {
            $out->node($this->target);
        }
        $out->symbol('=')->node($this->value);
    }
}
