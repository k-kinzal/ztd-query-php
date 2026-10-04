<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Statement\Expression\BindParameter;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\CurrentTime;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Raise;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\Exists;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InTable;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\DefinitionPosition;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\ProhibitedConstruct;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\ProhibitedExpression;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;

/**
 * Finds the constructs SQLite rejects inside the expressions of a definition.
 *
 * Rule: SQLITE-DEFINITION-LIMITS-001. SQLite resolves the expressions of a
 * CHECK constraint, a partial index WHERE clause, an index expression and a
 * generated column against the table itself and rejects, at every one of
 * these positions, a bound parameter and a subquery (an `IN table` operand
 * is a subquery, because SQLite reads it as one); at an index expression and
 * in a generated column also a qualified column reference, "the . operator";
 * and at an index expression, in a partial index WHERE clause and in a
 * generated column also a call of a function the library does not flag as
 * constant: `random`, `randomblob`, `last_insert_rowid`, `changes`,
 * `total_changes`, `current_time`, `current_timestamp`, `current_date` (the
 * keywords included), `sqlite_version`, `sqlite_source_id`,
 * `sqlite_compileoption_used`, `sqlite_compileoption_get` and
 * `load_extension`. The date functions given 'now' pass this check and fail
 * when the index or column is used, which is not modeled. Whether a function
 * that is not built in is deterministic is a registration on the connection
 * and not a fact of the model.
 *
 * A DEFAULT value must be constant in a different sense: it may call any
 * function but may not contain a bound parameter, a subquery, a column
 * reference (a double-quoted word included) or RAISE.
 *
 * Each kind of construct is reported once per expression. A subquery is
 * found at the expression that holds it and at the query itself; the inside
 * of a subquery is not examined, as SQLite stops at the subquery. Terminates: the
 * expression is walked once with an explicit stack.
 * Source: https://sqlite.org/lang_createtable.html#check_constraints,
 * https://sqlite.org/lang_createtable.html#the_default_clause,
 * https://sqlite.org/partialindex.html, https://sqlite.org/expridx.html,
 * https://sqlite.org/gencol.html (and `sqlite3ResolveSelfReference()` in
 * resolve.c and `sqlite3ExprIsConstantOrFunction()` in expr.c of the
 * release). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class Limits
{
    /**
     * The built-in functions the library registers without the constant flag, in lower case.
     */
    private const NON_DETERMINISTIC = [
        'random', 'randomblob', 'last_insert_rowid', 'changes', 'total_changes',
        'current_time', 'current_timestamp', 'current_date',
        'sqlite_version', 'sqlite_source_id', 'sqlite_compileoption_used', 'sqlite_compileoption_get',
        'load_extension',
    ];

    /**
     * The constructs each position rejects.
     */
    private const REJECTED = [
        'CheckConstraint' => [ProhibitedConstruct::Parameter, ProhibitedConstruct::Subquery],
        'PartialIndexWhere' => [ProhibitedConstruct::Parameter, ProhibitedConstruct::Subquery, ProhibitedConstruct::NonDeterministicFunction],
        'IndexExpression' => [ProhibitedConstruct::Parameter, ProhibitedConstruct::Subquery, ProhibitedConstruct::DotOperator, ProhibitedConstruct::NonDeterministicFunction],
        'GeneratedColumn' => [ProhibitedConstruct::Parameter, ProhibitedConstruct::Subquery, ProhibitedConstruct::DotOperator, ProhibitedConstruct::NonDeterministicFunction],
    ];

    /**
     * Answers the nodes of an expression in depth-first order, without the inside of a subquery.
     *
     * @return list<Node>
     */
    public function nodes(Scalar $expression): array
    {
        $nodes = [];
        $pending = [$expression];
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_reverse($value));
                continue;
            }
            if (!$value instanceof Node) {
                continue;
            }
            $nodes[] = $value;
            if ($value instanceof Query) {
                continue;
            }
            array_push($pending, ...array_reverse(array_values(get_object_vars($value))));
        }

        return $nodes;
    }

    /**
     * Answers the construct a node is, if it is one SQLite can reject.
     */
    public function classify(Node $node): ?ProhibitedConstruct
    {
        if ($node instanceof BindParameter) {
            return ProhibitedConstruct::Parameter;
        }
        if ($node instanceof Query || $node instanceof ScalarSubquery || $node instanceof Exists || $node instanceof InQuery || $node instanceof InTable) {
            return ProhibitedConstruct::Subquery;
        }
        if ($node instanceof ColumnUse && $node->qualifier !== null) {
            return ProhibitedConstruct::DotOperator;
        }
        $deterministic = !$node instanceof CurrentTime && (!$node instanceof FunctionCall || !in_array(strtolower($node->name->value), self::NON_DETERMINISTIC, true));

        return $deterministic ? null : ProhibitedConstruct::NonDeterministicFunction;
    }

    /**
     * Answers the constructs of an expression that its position rejects, each once, in order of first use.
     *
     * @return list<ProhibitedConstruct>
     */
    public function rejected(Scalar $expression, DefinitionPosition $position): array
    {
        $found = [];
        foreach ($this->nodes($expression) as $node) {
            $construct = $this->classify($node);
            if ($construct !== null && in_array($construct, self::REJECTED[$position->name], true) && !in_array($construct, $found, true)) {
                $found[] = $construct;
            }
        }

        return $found;
    }

    /**
     * Reports each construct of an expression that its position rejects.
     */
    public function report(Scalar $expression, DefinitionPosition $position, Derivation $derivation): void
    {
        foreach ($this->rejected($expression, $position) as $construct) {
            $derivation->report(new ProhibitedExpression($construct, $position));
        }
    }

    /**
     * Tells whether a DEFAULT expression is not constant in the sense of SQLite.
     */
    public function nonConstant(Scalar $expression): bool
    {
        foreach ($this->nodes($expression) as $node) {
            if ($node instanceof BindParameter || $node instanceof Query || $node instanceof InTable || $node instanceof ColumnUse || $node instanceof DoubleQuotedWord || $node instanceof Raise) {
                return true;
            }
        }

        return false;
    }
}
