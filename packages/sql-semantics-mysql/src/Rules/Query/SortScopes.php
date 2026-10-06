<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberForm;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;

/**
 * Derives the items of ORDER BY and GROUP BY.
 *
 * Rule: MYSQL-SORT-SCOPE-001. A select list position (MYSQL-ORDINAL-001)
 * denotes the output field at that position. An ORDER BY item that is a bare
 * unqualified name equal to the alias of a select list item denotes that
 * item, because ORDER BY searches the select list first. Every other item
 * sees the columns of the FROM clause first and the aliases of the select
 * list after them, which is how GROUP BY, HAVING and expressions in ORDER BY
 * resolve. Terminates: one pass over the items. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/select.html ("MySQL resolves an
 * unqualified column or alias reference in ORDER BY clauses by searching in
 * the select_expr values, then in the columns of the tables in the FROM
 * clause. For GROUP BY or HAVING clauses, it searches the FROM clause before
 * searching in the select_expr values"). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SortScopes
{
    /**
     * Requires every integer literal item to be given as a select list position.
     *
     * @param list<OrderItem> $items
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When an integer literal is an ordinary item
     */
    public function check(array $items): void
    {
        foreach ($items as $item) {
            Check::input(!$item->expression instanceof NumberLiteral || $item->expression->form !== NumberForm::Integer, 'An integer in ORDER BY or GROUP BY is a select list position.');
        }
    }

    /**
     * Derives the items in the given environment, whose aliases are the aliased output fields.
     *
     * @param list<OrderItem> $items
     * @param list<Field|OpenStar> $projection The output of the query, in order
     * @param bool $aliasFirst Whether a bare name looks at the aliases first, as in ORDER BY
     * @return list<ScalarFact> The facts of the items, in order
     */
    public function derive(array $items, Derivation $derivation, Environment $environment, array $projection, bool $aliasFirst): array
    {
        $known = [];
        foreach ($projection as $field) {
            if (!$field instanceof Field) {
                break;
            }
            $known[] = $field;
        }
        $open = count($known) < count($projection);
        $facts = [];
        foreach ($items as $item) {
            $word = $aliasFirst ? $this->word($item->expression) : null;
            if ($item->expression instanceof OutputOrdinal) {
                $facts[] = $derivation->scalar($item->expression, new Environment($derivation->context, $environment->outer, $open ? $environment->relations : [], [], $known));
            } elseif ($word !== null && $environment->aliased($word) !== []) {
                $facts[] = $derivation->scalar($item->expression, new Environment($derivation->context, $environment->outer, [], [], $environment->aliased($word)));
            } else {
                $facts[] = (new Operands())->single($derivation->scalar($item->expression, $environment), $derivation);
            }
        }

        return $facts;
    }

    /**
     * Answers the name of an item that is a bare unqualified column reference, possibly in parentheses.
     */
    public function word(Scalar $expression): ?Name
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }

        return $expression instanceof ColumnUse && $expression->qualifier === null ? $expression->name : null;
    }
}
