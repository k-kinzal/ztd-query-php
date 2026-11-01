<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Query\AliasSpelling;
use SqlSemantics\Platform\PostgreSql\Rules\Resolution\FromScope;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation;

/**
 * A join in parentheses, optionally renamed.
 *
 * Without an alias the parentheses only group. An alias names the joined
 * row and hides the FROM items inside: they can no longer be referred to by
 * their names. The facts follow PG-JOIN-001.
 * Source: https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-JOIN. Status: Implemented.
 *
 * @visibility public
 * @example Reading a renamed join
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 FROM (a CROSS JOIN b) AS j');
 *     [$query->from->alias->value, $query->toString()] // => ['j', 'SELECT 1 FROM (a CROSS JOIN b) AS j']
 */
final class ParenthesizedJoin implements Relation, \SqlSemantics\Statement\TableUse
{
    use \SqlSemantics\Statement\Semantics\RelationSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<Name> The column names written after the correlation name
     */
    public readonly array $columns;

    /**
     * @param Join|ParenthesizedJoin $join The join inside the parentheses; parentheses inside have no alias
     * @param Name|null $alias The correlation name
     * @param list<Name> $columns The column names written after the correlation name
     */
    public function __construct(public readonly Join|ParenthesizedJoin $join, public readonly ?Name $alias = null, array $columns = [], ?RelationFact $relationInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($relationInformation !== null) {
            $this->initializeRelationInformation($relationInformation);
        }
        $this->columns = Check::listOf($columns, Name::class, 'Column aliases are names.');
        Check::input($alias !== null || $this->columns === [], 'Column aliases are written after a correlation name.');
        Check::input(!$join instanceof self || $join->alias === null, 'Parentheses inside parentheses carry no alias.');
    }

    /**
     * Derives the join and the columns of the occurrence.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new FromScope())->enter($this, $derivation, $environment, [])->fact;
    }

    /**
     * Writes the join in parentheses and the alias.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->node($this->join)->symbol(')');
        (new AliasSpelling())->write($out, $this->alias, $this->columns);
    }
}
