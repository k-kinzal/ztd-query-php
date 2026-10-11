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
 * `NAMES = expr` in a SET, which the grammar reads and the server refuses.
 *
 * Rule: MYSQL-SET-NAMES-002. A stored program variable named `names` is
 * distinguished from the connection character set statement: the
 * server raises ER_SP_BAD_VAR_SHADOW when the enclosing program declares a
 * variable `names` and a syntax error otherwise; SET NAMES takes a
 * character set name (SetNames). The expression is derived at a position
 * that sees no relation. Diagnostics: UtilityRule::NamesExpression or
 * UtilityRule::NamesShadow. MySQL 8.0 and later read the whole assignment
 * list before reporting this failure; older releases stop at this item.
 * Terminates: the value is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-names.html.
 * Error order and local variable behavior verified against MySQL 8.4.7.
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
        $problem = $this->problem($derivation);
        $derivation->report($problem);
        if (in_array($derivation->context->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true)) {
            $derivation->warn(new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($problem, true));
        }
    }

    /**
     * Identifies whether the assignment shadows a local variable at this position.
     */
    public function problem(Derivation $derivation): UtilityMisuse
    {
        $variable = (new \SqlSemantics\Platform\MySql\Rules\ProgramVariables())->find($derivation->environment(), new \SqlSemantics\Statement\Identifier\Name('names'));

        return new UtilityMisuse($variable === null ? UtilityRule::NamesExpression : UtilityRule::NamesShadow, $this);
    }

    /**
     * Writes NAMES, an equals sign and the expression.
     */
    public function render(Output $out): void
    {
        $out->keyword('NAMES')->symbol('=')->node($this->value);
    }
}
