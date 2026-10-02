<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Relation;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\AmbiguousColumn;
use SqlSemantics\Statement\Reference\AmbiguousTable;
use SqlSemantics\Statement\Reference\CandidateColumn;
use SqlSemantics\Statement\Reference\MissingColumn;
use SqlSemantics\Statement\Reference\NamedAlias;
use SqlSemantics\Statement\Reference\OuterLookup;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\Schema\Catalog;

/**
 * The relation occurrences visible at one column lookup site.
 * @visibility public
 * @example Resolving a name with no visible relations
 *     $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main')), complete: false);
 *     (new \SqlSemantics\Statement\Relation\Scope($catalog))->resolve(new \SqlSemantics\Statement\Identifier\Name('id')) === \SqlSemantics\Statement\Reference\MissingColumn::Value // => true
 */
final class Scope
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * @var list<TableReference>
     */
    public readonly array $tables;

    /**
     * Declarations shared by this lookup scope and all of its lexical parents.
     */
    public readonly Catalog $catalog;

    /**
     * The next enclosing namespace, independent of statement execution order.
     */
    public readonly self|SqliteAliasScope|null $parent;

    /**
     * Keeps relation occurrences separate, including duplicate aliases and self joins.
     */
    public function __construct(Catalog|self|SqliteAliasScope $catalog, TableReference ...$tables)
    {
        $this->parent = $catalog instanceof Catalog ? null : $catalog;
        $this->catalog = $catalog instanceof Catalog ? $catalog : $catalog->catalog;
        $this->tables = array_values($tables);
        $occurrences = [];
        foreach ($tables as $table) {
            \SqlSemantics\Statement\Validation\Check::input($table->catalog === $this->catalog, 'Every relation must use the scope declaration context.');
            \SqlSemantics\Statement\Validation\Check::input(!isset($occurrences[spl_object_id($table)]), 'Each relation use must have its own occurrence identity.');
            $occurrences[spl_object_id($table)] = true;
        }
    }

    /**
     * Derives column ownership solely from this scope and its supplied declarations.
     */
    public function resolve(Name $name, ?QualifiedName $qualifier = null): ResolvedColumn|CandidateColumn|MissingColumn|AmbiguousColumn|AmbiguousTable|NamedAlias
    {
        $local = $this->local($name, $qualifier);
        if ($local instanceof CandidateColumn && $this->parent !== null) {
            foreach ($local->possibilities as $possibility) {
                if ($possibility instanceof ResolvedColumn) {
                    return $local;
                }
            }
            return new CandidateColumn($local->first, ...[...array_slice($local->possibilities, 1), new OuterLookup($this->parent, $name, $qualifier)]);
        }
        return $local instanceof MissingColumn ? ($this->parent?->resolve($name, $qualifier) ?? $local) : $local;
    }

    /**
     * Searches only this query's input relations, before aliases or enclosing queries.
     */
    public function local(Name $name, ?QualifiedName $qualifier = null): ResolvedColumn|CandidateColumn|MissingColumn|AmbiguousColumn|AmbiguousTable
    {
        $matches = [];
        $candidates = [];
        $conflicts = [];
        foreach ($this->tables as $relation) {
            if (!$relation->matches($qualifier)) {
                continue;
            }
            if (count($relation->declarations) > 1) {
                $conflicts[] = $relation;
                continue;
            }
            if ($relation->declarations === [] && !$this->catalog->complete) {
                $candidates[] = $relation;
            }
            foreach ($relation->declarations as $table) {
                foreach ($table->matchingColumns($name->value, $this->catalog->columnNames) as $column) {
                    $matches[] = new ResolvedColumn($relation, $table, $column);
                }
            }
        }
        if ($conflicts !== []) {
            return new AmbiguousTable($conflicts[0], ...array_slice($conflicts, 1));
        }
        if (count($matches) > 1) {
            return new AmbiguousColumn($matches[0], $matches[1], ...array_slice($matches, 2));
        }
        if ($candidates !== []) {
            return new CandidateColumn($candidates[0], ...[...array_slice($candidates, 1), ...$matches]);
        }
        return $matches[0] ?? MissingColumn::Value;
    }
}
