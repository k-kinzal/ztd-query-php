<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Relation;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Projection\Field;
use SqlSemantics\Statement\Projection\Fields;
use SqlSemantics\Statement\Reference\AmbiguousColumn;
use SqlSemantics\Statement\Reference\AmbiguousTable;
use SqlSemantics\Statement\Reference\CandidateColumn;
use SqlSemantics\Statement\Reference\MissingColumn;
use SqlSemantics\Statement\Reference\NamedAlias;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\Schema\Catalog;

/**
 * SQLite's input-column, projection-alias, then outer-query lookup order.
 * @visibility public
 * @example Creating an empty alias namespace
 *     $scope = new \SqlSemantics\Statement\Relation\Scope(new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main'))));
 *     (new \SqlSemantics\Statement\Relation\SqliteAliasScope(new \SqlSemantics\Statement\Projection\Fields($scope)))->scope === $scope // => true
 */
final class SqliteAliasScope
{
    /**
     * @var list<Field>
     */
    public readonly array $aliases;
    /**
     * The query input namespace, before adding its visible output aliases.
     */
    public readonly Scope $scope;
    /**
     * The same declarations used by the query's inputs.
     */
    public readonly Catalog $catalog;

    /**
     * Only aliases visible at this lookup site enter the namespace.
     */
    public function __construct(public readonly Fields $projection, Field ...$aliases)
    {
        $this->scope = $projection->scope;
        $this->catalog = $this->scope->catalog;
        $this->aliases = array_values($aliases);
        foreach ($aliases as $alias) {
            assert($alias->alias !== null && in_array($alias, $projection->items, true), 'A visible alias must belong to this projection.');
        }
    }

    /**
     * A nearer alias takes priority over all names in more distant query scopes.
     */
    public function resolve(Name $name, ?QualifiedName $qualifier = null): ResolvedColumn|CandidateColumn|MissingColumn|AmbiguousColumn|AmbiguousTable|NamedAlias
    {
        $local = $this->scope->local($name, $qualifier);
        if ($qualifier === null && ($local instanceof MissingColumn || $local instanceof CandidateColumn)) {
            foreach ($this->aliases as $alias) {
                assert($alias->alias !== null, 'A visible alias has a declared name.');
                if ($this->catalog->columnNames->equal($alias->alias->value, $name->value)) {
                    $reference = new NamedAlias($this->projection, $alias, $name);
                    return $local instanceof CandidateColumn ? $local->withFallback($reference) : $reference;
                }
            }
        }
        return $this->scope->resolve($name, $qualifier);
    }
}
