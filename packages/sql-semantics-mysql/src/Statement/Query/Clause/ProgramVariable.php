<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Clause;

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
 * A local variable of a stored program named where only a variable is accepted: a LIMIT operand or an INTO target.
 *
 * Rule: MYSQL-PROGRAM-VARIABLE-001. A bare name in LIMIT or after INTO
 * denotes a variable or parameter declared by the enclosing stored program;
 * outside one the server reports an undeclared variable. The declaration is
 * runtime program state the analysis context does not hold, so the type and
 * the NULL fact depend on it. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/select.html (LIMIT),
 * https://dev.mysql.com/doc/refman/8.4/en/select-into.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ProgramVariable implements Scalar
{
    use Snapshot;

    /**
     * @param Name $name The variable name
     */
    public function __construct(public readonly Name $name)
    {
    }

    /**
     * Depends on the declaration of the variable in the enclosing stored program.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact(new Dependent([new SessionState('stored program variable ' . $this->name->value)]), Nullability::Dependent);
    }

    /**
     * Writes the name.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Column);
    }
}
