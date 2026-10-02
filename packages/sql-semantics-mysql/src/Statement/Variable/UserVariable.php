<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Variable;

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
 * A user-defined variable read as a value: `@name`.
 *
 * Variable names are compared without regard to letter case; the name is
 * kept as written.
 *
 * Rule: MYSQL-USER-VARIABLE-001. Facts: the type and the NULL fact are those
 * of the value the session last assigned, which no context holds; the fact
 * names the variable as missing session state. Diagnostics: none.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/user-variables.html. Status: Implemented.
 *
 * @visibility public
 * @example Naming the session state a variable depends on
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a = @total');
 *     [$query->statement->where->right->name->value, $query->facts->scalar($query->statement->where->right)->type->missing[0]->describe()] // => ['total', 'the session state: user variable @total']
 */
final class UserVariable implements Scalar
{
    use Snapshot;

    /**
     * @param Name $name The variable name without the at sign
     */
    public function __construct(public readonly Name $name)
    {
    }

    /**
     * Derives the dependence on the session.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact(new Dependent([new SessionState('user variable @' . $this->name->value)]), Nullability::Dependent);
    }

    /**
     * Writes the at sign and the name without a space between them.
     */
    public function render(Output $out): void
    {
        $out->symbol('@')->glue()->name($this->name, NameUse::Label);
    }
}
