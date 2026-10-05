<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Query\RelationNames;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;

/**
 * A query in parentheses used as query input.
 *
 * Rule: SQLITE-DERIVED-QUERY-001. The query is derived in the environment
 * that encloses the FROM clause: it sees the enclosing queries and no
 * sibling of its own FROM clause. Its columns are the result columns of the
 * query, named by SQLITE-RELATION-NAME-001.
 * Source: https://sqlite.org/lang_select.html#the_from_clause. Status: Implemented.
 *
 * @visibility public
 * @example Reading a derived query
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT x FROM (SELECT 1 AS x) AS d');
 *     [$query->statement->from->alias->value, $query->field('x')->type->descriptor] // => ['d', \SqlSemantics\Platform\Sqlite\Statement\Type\Storage::Integer]
 */
final class DerivedQuery implements Relation
{
    use Snapshot;

    /**
     * @param Query $query The query
     * @param Name|null $alias The correlation name
     * @param bool $as Whether the keyword AS introduces the alias; SQLite names a column after the text of an expression, which includes it
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When AS is left out without an alias
     */
    public function __construct(public readonly Query $query, public readonly ?Name $alias = null, public readonly bool $as = true)
    {
        Check::input($as || $alias !== null, 'AS is left out only before an alias.');
    }

    /**
     * Derives the query and the row shape its result gives the occurrence.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return new RelationFact((new RelationNames())->shape($derivation->query($this->query, $environment), $this->query, $derivation));
    }

    /**
     * Writes the query in parentheses and the correlation name.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->node($this->query)->symbol(')');
        if ($this->alias !== null) {
            if ($this->as) {
                $out->keyword('AS');
            }
            $out->name($this->alias, NameUse::Alias);
        }
    }
}
