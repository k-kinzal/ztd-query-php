<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Variable;

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
 * @visibility SqlSemantics
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
