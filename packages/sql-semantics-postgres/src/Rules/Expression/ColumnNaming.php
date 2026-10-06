<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Expression;

use SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Collation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\CaseExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Indirection;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\TypedLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * Names an unaliased result column after its expression, with the strength PostgreSQL gives each name.
 *
 * Rule: PG-COLUMN-NAMING-001 (`FigureColname`). A column reference, a
 * function call and the function-like constructs name the column firmly
 * (strength 2). A cast names it after its operand when the operand names it
 * firmly, and otherwise weakly (strength 1) after the type; a typed constant
 * is a cast of a constant. CASE names it after its ELSE result when that
 * names it firmly, and otherwise weakly `case`. An indirection names it
 * after the last field it selects, and otherwise as its base does;
 * parentheses and COLLATE pass the operand's name through. Any other
 * expression gives no name (strength 0), and the query names the column
 * `?column?`. Termination: each step descends one operand of a finite tree.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-SELECT-LIST,
 * https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/parse_target.c. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ColumnNaming
{
    /**
     * Answers the name an expression gives an unaliased result column, or null.
     */
    public function name(Scalar $expression): ?Name
    {
        return $this->figure($expression)[0];
    }

    /**
     * Answers the name an expression gives a result column and its strength: 0 none, 1 weak, 2 firm.
     *
     * @return array{Name|null, int}
     */
    public function figure(Scalar $expression): array
    {
        if ($expression instanceof Grouped || $expression instanceof Collation) {
            return $this->figure($expression->operand);
        }
        if ($expression instanceof Cast) {
            $operand = $this->figure($expression->operand);

            return $operand[1] > 1 ? $operand : [$expression->type->designation->catalogName(), 1];
        }
        if ($expression instanceof TypedLiteral) {
            return [$expression->type->designation->catalogName(), 1];
        }
        if ($expression instanceof CaseExpression) {
            $default = $expression->default === null ? [null, 0] : $this->figure($expression->default);

            return $default[1] > 1 ? $default : [new Name('case'), 1];
        }
        if ($expression instanceof Indirection) {
            $field = null;
            foreach ($expression->steps as $step) {
                $field = $step->field() ?? $field;
            }

            return $field !== null ? [$field, 2] : $this->figure($expression->base);
        }
        $name = $expression instanceof OutputNaming ? $expression->outputName() : null;

        return [$name, $name === null ? 0 : 2];
    }
}
