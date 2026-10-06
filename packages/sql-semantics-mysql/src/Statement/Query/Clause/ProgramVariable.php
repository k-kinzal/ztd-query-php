<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Clause;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;

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
 * @visibility public
 * @example Reading a program variable used as a row count
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t LIMIT n');
 *     $query->statement->limit->count->name->value // => 'n'
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
