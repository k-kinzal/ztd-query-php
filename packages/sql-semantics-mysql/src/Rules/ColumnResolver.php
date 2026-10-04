<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules;

use SqlSemantics\Platform\MySql\Statement\Name\AmbiguousAlias;
use SqlSemantics\Resolution\ColumnLookup;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\LookupLevel;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\AmbiguousColumn;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\Resolution;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Validation\Equivalence;

/**
 * Resolves a column name the way MySQL does: the columns of a query first, then its select list aliases.
 *
 * Rule: MYSQL-COLUMN-LOOKUP-001. The lookup starts at the innermost query and
 * moves outwards one query at a time. At one query the relation occurrences
 * the qualifier admits are searched as in CORE-COLUMN-LOOKUP-001: one known
 * slot resolves, several are ambiguous, and an incompletely known occurrence
 * at that or a nearer query makes the outcome conditional. When no slot has
 * the name, an unqualified name is searched among the select list aliases
 * the position may use (GROUP BY, HAVING, ORDER BY): one item, or several
 * items computing the same expression, resolve to that item; several
 * different items are ambiguous (ER_NON_UNIQ_ERROR). While an incompletely
 * known occurrence could still own the name, the alias is not chosen and the
 * outcome is conditional. Only a name found neither way continues outwards.
 * Terminates: the scopes form a finite chain. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/select.html ("For GROUP BY or
 * HAVING clauses, it searches the FROM clause before searching in the
 * select_expr values"), https://dev.mysql.com/doc/refman/8.4/en/problems-with-alias.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ColumnResolver
{
    /**
     * Resolves a column name written with an optional relation qualifier.
     */
    public function find(Environment $environment, Name $column, ?QualifiedName $qualifier = null): Resolution
    {
        $lookup = new ColumnLookup();
        $open = [];
        $depth = 0;
        for ($scope = $environment; $scope !== null; $scope = $scope->outer) {
            $level = new LookupLevel($scope, $column, $qualifier, $depth);
            $found = $level->found();
            $open = [...$open, ...$level->open()];
            if ($found !== []) {
                if ($open !== []) {
                    return $lookup->conditional($column, $found, $open);
                }

                return count($found) === 1 ? $found[0] : new AmbiguousColumn($column, $found);
            }
            $aliases = $qualifier === null ? $scope->aliased($column) : [];
            if ($aliases !== []) {
                return $open === [] ? $this->alias($column, $aliases) : $lookup->conditional($column, [], $open);
            }
            $depth++;
        }

        return $open === [] ? new MissingColumn($column, $qualifier) : $lookup->conditional($column, [], $open);
    }

    /**
     * Chooses the select list item an alias names: the only one, or the first of items that compute the same expression.
     *
     * @param non-empty-list<Field> $fields
     */
    public function alias(Name $column, array $fields): AliasTarget|AmbiguousAlias
    {
        $first = $fields[0];
        $equivalence = new Equivalence();
        foreach ($fields as $field) {
            if ($field->expression === null || $first->expression === null || $equivalence->difference($first->expression, $field->expression) !== null) {
                return count($fields) === 1 ? new AliasTarget($first) : new AmbiguousAlias($column, $fields);
            }
        }

        return new AliasTarget($first);
    }
}
