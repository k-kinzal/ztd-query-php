<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\FromScope;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;

/**
 * A FROM clause term list written in parentheses and used as one term.
 *
 * Rule: SQLITE-NESTED-INPUT-001. The terms inside are joined first. Their
 * relations stay visible under their own names; a correlation name on the
 * parentheses names the joined result as well. Around a single term the
 * parentheses and their correlation name replace the correlation name of
 * that term.
 * Source: https://sqlite.org/lang_select.html#the_from_clause. Status: Implemented.
 *
 * @visibility public
 * @example Reading a parenthesised join
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 FROM a LEFT JOIN (b JOIN c) AS x');
 *     [$query->statement->from->steps[0]->relation->alias->value, count($query->statement->from->steps[0]->relation->relation->steps)] // => ['x', 1]
 */
final class NestedInput implements Relation
{
    use Snapshot;

    /**
     * @param Relation $relation The relation written inside the parentheses
     * @param Name|null $alias The correlation name of the parentheses
     * @param bool $as Whether the keyword AS introduces the alias; SQLite names a column after the text of an expression, which includes it
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When AS is left out without an alias
     */
    public function __construct(public readonly Relation $relation, public readonly ?Name $alias = null, public readonly bool $as = true)
    {
        Check::input($as || $alias !== null, 'AS is left out only before an alias.');
    }

    /**
     * Derives the relation inside and the shape the parentheses contribute.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new FromScope())->enter($this, $derivation, $environment, [], true)->fact;
    }

    /**
     * Writes the relation in parentheses and the correlation name.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->node($this->relation)->symbol(')');
        if ($this->alias !== null) {
            if ($this->as) {
                $out->keyword('AS');
            }
            $out->name($this->alias, NameUse::Alias);
        }
    }
}
