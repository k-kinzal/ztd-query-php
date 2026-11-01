<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Clause;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A local variable of a stored program named where only a variable is accepted: a LIMIT operand or an INTO target.
 *
 * Rule: MYSQL-PROGRAM-VARIABLE-001. A bare name in LIMIT or after INTO
 * denotes a variable or parameter declared by the enclosing stored program;
 * outside one the server reports an undeclared variable. A statement of a
 * running program is given the variables in scope (Settings::$program): the
 * name then has the declared type of the innermost variable of the name and
 * can be NULL. During program definition the lexical environment resolves
 * the same declaration, and an absent declaration is reported. Outside a
 * program, an absent declaration leaves the type dependent on program state. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/select.html (LIMIT),
 * https://dev.mysql.com/doc/refman/8.4/en/select-into.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a program variable used as a row count
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t LIMIT n');
 *     $query->query->limit->count->name->value // => 'n'
 */
final class ProgramVariable implements Operand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Name $name The variable name
     */
    public function __construct(public readonly Name $name, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
    }

    /**
     * Resolves the nearest program declaration during definition or execution.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $declared = (new \SqlSemantics\Platform\MySql\Rules\ProgramVariables())->find($environment, $this->name);
        if ($declared instanceof \SqlSemantics\Statement\Reference\Column\ResolvedColumn) {
            return new ScalarFact($declared->slot->type, $declared->slot->nullability, $declared);
        }
        if ($derivation->inProgram()) {
            $derivation->report(new \SqlSemantics\Platform\MySql\Statement\Query\Problem\UndeclaredVariable($this->name));
        }
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
