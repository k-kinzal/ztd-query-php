<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\DerivedShapes;
use SqlSemantics\Platform\MySql\Rules\Typing\Materialization;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Platform\MySql\Statement\Relation\TableSource as Relation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;

/**
 * A derived table: `[LATERAL] (query) [AS] alias [(columns)]`.
 *
 * The query is the one inside the parentheses the syntax requires.
 *
 * Rule: MYSQL-DERIVED-TABLE-001. From MySQL 8.0 the query sees the enclosing queries and,
 * when LATERAL is written, the tables to its left in the same FROM clause
 * (MYSQL-FROM-SCOPE-001). The columns are those of the query, renamed by the
 * column list (MYSQL-DERIVED-SHAPES-001). The correlation name is mandatory;
 * an empty quoted alias remains a valid name. Source: https://dev.mysql.com/doc/refman/8.4/en/derived-tables.html,
 * https://dev.mysql.com/doc/refman/8.4/en/lateral-derived-tables.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a derived table
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT x FROM (SELECT 1) AS d (x)');
 *     [$query->query->from->alias->value, $query->query->from->columns[0]->value] // => ['d', 'x']
 * @example Refusing an option that no derived table accepts
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::HighPriority], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral())]);
 *     new \SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable($query, new \SqlSemantics\Statement\Identifier\Name('t')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing ALL with DISTINCT in a derived relation, which every release refuses there
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::All, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Distinct], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]);
 *     new \SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable($query, new \SqlSemantics\Statement\Identifier\Name('t')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing the cache modifier in a derived relation
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Cache], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral())]);
 *     new \SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable($query, new \SqlSemantics\Statement\Identifier\Name('t')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DerivedTable implements Relation, \SqlSemantics\Statement\TableUse
{
    use \SqlSemantics\Statement\Semantics\RelationSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<Name> The column names in written order; empty when the query names the columns
     */
    public readonly array $columns;

    /**
     * @param Query $query The query that produces the rows
     * @param Name $alias The correlation name
     * @param list<Name> $columns The column names
     * @param bool $lateral Whether LATERAL is written
     * @param AliasMark $mark What is written before the alias
     */
    public function __construct(public readonly Query $query, public readonly Name $alias, array $columns = [], public readonly bool $lateral = false, public readonly AliasMark $mark = AliasMark::As, ?RelationFact $relationInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($relationInformation !== null) {
            $this->initializeRelationInformation($relationInformation);
        }
        $this->columns = Check::listOf($columns, Name::class, 'The column list of a derived table holds names.');
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Query\Structure\DerivedOptions())->problem($query) === null, 'A derived query cannot carry statement-level SELECT modifiers.');
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Query\Structure\StatementQuery())->problem($query) === null, 'A derived query cannot combine ALL and DISTINCT in one query block.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the query and the row shape of the derived table.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        $query = $derivation->query($this->query, $environment);

        return new RelationFact((new DerivedShapes())->shape($query, $this->columns, $derivation, (new Materialization())->mergeable($this->query, $derivation->context->profile->grammar, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::of($derivation->context)->derivedMerge) ? null : $this->query));
    }

    /**
     * Writes the derived table.
     */
    public function render(Output $out): void
    {
        if ($this->lateral) {
            $out->keyword('LATERAL');
        }
        $out->symbol('(')->node($this->query)->symbol(')');
        $this->mark->write($out);
        $out->name($this->alias, NameUse::Alias);
        if ($this->columns !== []) {
            $out->symbol('(');
            foreach ($this->columns as $position => $column) {
                if ($position > 0) {
                    $out->symbol(',');
                }
                $out->name($column, NameUse::Column);
            }
            $out->symbol(')');
        }
    }
}
