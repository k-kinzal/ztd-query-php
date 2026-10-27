<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Variable;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Utility\SystemNames;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;

/**
 * The system variable assigned by SET or an INSTALL COMPONENT setting.
 *
 * This operand is not a value expression. PERSIST and PERSIST_ONLY select
 * assignment scopes only. The enclosing assignment resolves writability,
 * scope and structured names in the release's diagnostic order; deriving
 * the target never reads the variable or emits a read deprecation warning.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-variable.html.
 *
 * @visibility public
 * @example Distinguishing an assignment target from a value read
 *     $target = new \SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget(new \SqlSemantics\Statement\Identifier\Name('autocommit'));
 *     $target instanceof \SqlSemantics\Statement\Scalar // => false
 */
final class SystemTarget implements Operand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Name $name The assigned variable name
     * @param VariableScope|null $scope The explicitly requested assignment scope
     * @param Name|null $instance The instance or component name
     */
    public function __construct(public readonly Name $name, public readonly ?VariableScope $scope = null, public readonly ?Name $instance = null, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
    }

    /**
     * Records the state referred to by the target, without reading its value.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $subject = 'system variable @@' . ($this->scope === null ? '' : $this->scope->value . '.') . ($this->instance === null ? '' : $this->instance->value . '.') . $this->name->value;

        return new ScalarFact(new Dependent([new SessionState($subject)]), Nullability::Dependent);
    }

    /**
     * Checks a structured assignment at the point chosen by its parent.
     */
    public function structured(Derivation $derivation): void
    {
        (new SystemNames())->structured($this->name, $this->instance, true, $derivation);
    }

    /**
     * Writes the name with its assignment scope and optional instance.
     */
    public function render(Output $out): void
    {
        (new SystemNames())->render($this->name, $this->scope, $this->instance, $out);
    }
}
