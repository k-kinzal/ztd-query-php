<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Set;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * `NAMES = expr` in a SET of MySQL 5.6 and 5.7, which the grammar reads and the server refuses.
 *
 * Rule: MYSQL-SET-NAMES-002. The grammar of MySQL 5.x has the production
 * so that a stored program variable named `names` can be reported: the
 * server raises ER_SP_BAD_VAR_SHADOW when the enclosing program declares a
 * variable `names` and a syntax error otherwise; SET NAMES takes a
 * character set name (SetNames). The expression is derived at a position
 * that sees no relation. Diagnostics: UtilityRule::NamesExpression.
 * Terminates: the value is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/set-names.html,
 * the `NAMES_SYM equal expr` action of sql/sql_yacc.yy (5.7).
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the refusal
 *     $set = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->analyze("SET NAMES = 'utf8'");
 *     [$set->facts->diagnostics[0]->rule, $set->toString()] // => [\SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule::NamesExpression, "SET NAMES = 'utf8'"]
 */
final class NamesExpression implements SetItem
{
    use Snapshot;

    /**
     * @param Scalar $value The expression written after the equals sign
     */
    public function __construct(public readonly Scalar $value)
    {
    }

    /**
     * Derives the expression and reports the refusal.
     */
    public function deriveItem(Derivation $derivation): void
    {
        $derivation->scalar($this->value, $derivation->environment());
        $derivation->report(new UtilityMisuse(UtilityRule::NamesExpression));
    }

    /**
     * Writes NAMES, an equals sign and the expression.
     */
    public function render(Output $out): void
    {
        $out->keyword('NAMES')->symbol('=')->node($this->value);
    }
}
