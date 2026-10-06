<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Rules\Query\ItemNaming;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Shape\Field;

/**
 * Derives the column names of a view without a column list from the select list of its first query block.
 *
 * Rule: MYSQL-VIEW-COLUMNS-001. A column is named like the output field of
 * its query (MYSQL-SELECT-ITEM-NAME-001), with two corrections the server
 * makes for the names it generated, that is for the items that have no
 * alias and are not column references. First a generated name that is not
 * a valid column name (empty, ending in a space character, or longer than
 * 64 characters) becomes `Name_exp_N`, N being the position of the field
 * (`make_valid_column_names`). Then, going through the fields in order, a
 * field whose name equals the name of an earlier field (without regard to
 * ASCII case) is renamed when its own name is generated, else the earlier
 * field is when its name is generated; the new name is the original name
 * with the prefix `Name_exp_` (`My_exp_` in 5.6 and 5.7), and with the
 * prefix and a counter (`Name_exp_1_`, `Name_exp_2_`, …) when that name is
 * taken by a field up to the current one, cut to 64 characters (to 191
 * bytes in 5.6 and 5.7) (`check_duplicate_names`,
 * `make_unique_view_field_name`). Two equal names that are not generated
 * are left for MYSQL-TABLE-PROBLEMS-001 to report. A field whose name is
 * not fixed keeps no name. Terminates: each renaming loop stops at the
 * first counter whose name is free, and at most one name per field
 * precedes it. Source: sql/sql_view.cc of each release,
 * https://dev.mysql.com/doc/refman/8.4/en/create-view.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ViewColumns
{
    /**
     * @param LanguageProfile $profile The profile whose release the names follow
     * @param Comparison $names The comparison of column names
     */
    public function __construct(private readonly LanguageProfile $profile, private readonly Comparison $names)
    {
    }

    /**
     * Answers the name of each view column, null when it is not fixed.
     *
     * @param list<Field> $fields The output fields of the query, in order
     * @return list<Name|null>
     */
    public function names(Query $query, array $fields): array
    {
        $generated = $this->generated($query, $fields);
        $names = [];
        $original = [];
        foreach ($fields as $position => $field) {
            $name = $field->name?->value;
            if ($name !== null && $generated[$position] && !(new ColumnNameRule())->valid($name)) {
                $original[$position] = $name;
                $name = 'Name_exp_' . ($position + 1);
            }
            $names[] = $name;
        }
        foreach (array_keys($names) as $current) {
            foreach (array_keys($names) as $earlier) {
                if ($earlier === $current) {
                    break;
                }
                if ($names[$current] === null || $names[$earlier] === null || !$this->names->equal($names[$current], $names[$earlier])) {
                    continue;
                }
                $renamed = $generated[$current] ? $current : ($generated[$earlier] ? $earlier : null);
                if ($renamed !== null) {
                    $names[$renamed] = $this->unique($names, $renamed, $current, $original[$renamed] ?? (string) $names[$renamed]);
                }
            }
        }

        return array_values(array_map(static fn (?string $name): ?Name => $name === null ? null : new Name($name), $names));
    }

    /**
     * Tells for each field whether the server generated its name: an item without alias that is not a column reference.
     *
     * @param list<Field> $fields
     * @return list<bool>
     */
    public function generated(Query $query, array $fields): array
    {
        $items = [];
        foreach ($this->block($query)->items ?? [] as $item) {
            if ($item instanceof SelectExpression) {
                $items[spl_object_id($item->expression)] = $item;
            }
        }
        $naming = new ItemNaming($this->profile);
        $generated = [];
        foreach ($fields as $field) {
            $item = $field->expression === null ? null : $items[spl_object_id($field->expression)] ?? null;
            $generated[] = $item !== null && $item->alias === null && !$naming->own($item->expression) instanceof ColumnUse;
        }

        return $generated;
    }

    /**
     * Answers the first query block of a query, whose select list names the columns, or null when the query starts with VALUES or TABLE.
     */
    public function block(Query $query): ?Select
    {
        $next = $query;
        for (;;) {
            if ($next instanceof Select) {
                return $next;
            }
            $next = match (true) {
                $next instanceof ParenthesizedQuery, $next instanceof QueryStatement => $next->query,
                $next instanceof QueryExpression => $next->body,
                $next instanceof SetOperation, $next instanceof OrderedSetOperation, $next instanceof LeadingUnion => $next->left,
                default => null,
            };
            if ($next === null) {
                return null;
            }
        }
    }

    /**
     * Answers the name a renamed field gets: the prefix and the original name, with a counter when that name is taken by a field up to the current one.
     *
     * @param array<int, string|null> $names The names so far
     */
    public function unique(array $names, int $renamed, int $current, string $original): string
    {
        $legacy = $this->profile->grammar === GrammarRelease::MySql5651 || $this->profile->grammar === GrammarRelease::MySql5744;
        $prefix = $legacy ? 'My_exp_' : 'Name_exp_';
        for ($attempt = 0; ; $attempt++) {
            $candidate = $this->cut($prefix . ($attempt === 0 ? '' : $attempt . '_') . $original, $legacy);
            $taken = false;
            foreach ($names as $position => $name) {
                $taken = $taken || ($position !== $renamed && $name !== null && $this->names->equal($name, $candidate));
                if ($position === $current) {
                    break;
                }
            }
            if (!$taken) {
                return $candidate;
            }
        }
    }

    /**
     * Cuts a generated name to the length the release keeps: 64 characters, or 191 bytes in 5.6 and 5.7.
     */
    public function cut(string $name, bool $legacy): string
    {
        if ($legacy) {
            return substr($name, 0, 191);
        }

        return (new ColumnNameRule())->length($name) <= 64 ? $name : implode('', array_slice((array) preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY), 0, 64));
    }
}
