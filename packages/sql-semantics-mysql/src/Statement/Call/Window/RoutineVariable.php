<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Window;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing\SessionState;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;

/**
 * A local variable of a stored program used as the count of NTILE or the offset of LEAD and LAG.
 *
 * Rule: MYSQL-ROUTINE-VARIABLE-001. The server reads the name as a routine
 * variable (PTI_int_splocal); outside a stored program it is the error
 * ER_SP_UNDECLARED_VAR. The value is program state the context does not
 * hold. Terminates: a leaf.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-function-descriptions.html#function_ntile.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Depending on the program state
 *     (new \SqlSemantics\Platform\MySql\Statement\Call\Window\RoutineVariable(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('n')))->name->value // => 'n'
 */
final class RoutineVariable implements Scalar
{
    use Snapshot;

    /**
     * @param Name $name The variable name
     */
    public function __construct(public readonly Name $name)
    {
    }

    /**
     * Answers that the value depends on the state of the stored program.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact(new Dependent([new SessionState('routine variable ' . $this->name->value)]), Nullability::Dependent);
    }

    /**
     * Writes the name.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Column);
    }
}
