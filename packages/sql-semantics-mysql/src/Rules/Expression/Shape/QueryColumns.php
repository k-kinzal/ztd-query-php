<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression\Shape;

use SqlSemantics\Platform\MySql\Rules\Query\ItemNaming;
use SqlSemantics\Platform\MySql\Statement\Query as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;

/**
 * Column positions determined by query values without catalog declarations.
 *
 * Null for the whole list means that its width depends on a relation declaration.
 * A null member has a fixed position but a session-dependent output name.
 * Common tables are evaluated in lexical order; a recursive reference uses the
 * anchor's columns instead of recursively expanding its own definition.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class QueryColumns
{
    /**
     * @param array<string, list<Name|null>|null> $common Visible common-table outputs
     * @return list<Name|null>|null Structurally fixed positions and their known names
     */
    public function of(Query|M\Set\LeadingUnion $query, bool $exists = false, array $common = []): ?array
    {
        return match (true) {
            $query instanceof M\QueryStatement, $query instanceof M\ParenthesizedQuery => $this->of($query->query, $exists, $common),
            $query instanceof M\QueryExpression => $this->of($query->body, $exists, $this->common($query->with, $common)),
            $query instanceof M\Set\SetOperation, $query instanceof M\Set\OrderedSetOperation, $query instanceof M\Set\LeadingUnion => $this->of($query->left, $exists, $common),
            $query instanceof M\ValuesQuery, $query instanceof M\With\DeferredValuesQuery => array_map(static fn (int $index): Name => new Name('column_' . $index), array_keys($query->rows[0]->values)),
            $query instanceof M\Select => $exists ? array_fill(0, count($query->items), null) : $this->select($query, $common),
            $query instanceof M\ExplicitTable && $query->table->schema === null => $common[$query->table->name->value] ?? null,
            default => null,
        };
    }

    /**
     * Binds each definition to the declarations visible at its written position.
     *
     * @param array<string, list<Name|null>|null> $outer Enclosing common tables
     * @return array<string, list<Name|null>|null> Body scope, including shadowing definitions
     */
    public function common(?M\WithClause $with, array $outer = []): array
    {
        if (!$with instanceof M\With\With) {
            return $outer;
        }
        foreach ($with->tables as $table) {
            if ($with->recursive) {
                $outer[$table->name->value] = null;
            }
            $outer[$table->name->value] = $table->columns !== [] ? $table->columns : $this->of($table->query, common: $outer);
        }
        return $outer;
    }

    /**
     * Expands only stars with a structurally known source.
     *
     * @param array<string, list<Name|null>|null> $common Visible common-table outputs
     * @return list<Name|null>|null Projection positions, without resolving scalar names
     */
    public function select(M\Select $query, array $common = []): ?array
    {
        $columns = [];
        $relations = new RelationColumns();
        foreach ($query->items as $item) {
            $added = match (true) {
                $item instanceof M\SelectExpression => [$this->name($item)],
                $item instanceof M\Star => $relations->of($query->from, $common),
                default => $relations->qualified($query->from, $item->table, $common),
            };
            if ($added === null) {
                return null;
            }
            array_push($columns, ...$added);
        }
        return $columns;
    }

    /**
     * Keeps names that do not change between legacy and modern interpretation.
     * Character-set-dependent names stay unknown until session resolution.
     */
    public function name(M\SelectExpression $item): ?Name
    {
        $profile = $item->spellingProfile ?? new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847);
        $modern = (new ItemNaming($profile))->name($item);
        $legacy = (new ItemNaming($item->spellingProfile ?? new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql5651)))->name($item);
        return $modern instanceof Name && $legacy instanceof Name && $modern->value === $legacy->value ? $modern : null;
    }
}
