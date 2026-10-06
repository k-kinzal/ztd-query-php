<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\DerivedShapes;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;

/**
 * A derived table: `[LATERAL] (query) [AS] alias [(columns)]`.
 *
 * The query is the one inside the parentheses the syntax requires.
 *
 * Rule: MYSQL-DERIVED-TABLE-001. The query sees the enclosing queries and,
 * when LATERAL is written, the tables to its left in the same FROM clause
 * (MYSQL-FROM-SCOPE-001). The columns are those of the query, renamed by the
 * column list (MYSQL-DERIVED-SHAPES-001). A derived table without alias is
 * reported. Source: https://dev.mysql.com/doc/refman/8.4/en/derived-tables.html,
 * https://dev.mysql.com/doc/refman/8.4/en/lateral-derived-tables.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a derived table
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT x FROM (SELECT 1) AS d (x)');
 *     [$query->statement->from->alias?->value, $query->statement->from->columns[0]->value] // => ['d', 'x']
 */
final class DerivedTable implements Relation
{
    use Snapshot;

    /**
     * @var list<Name> The column names in written order; empty when the query names the columns
     */
    public readonly array $columns;

    /**
     * @param Query $query The query that produces the rows
     * @param Name|null $alias The correlation name
     * @param list<Name> $columns The column names
     * @param bool $lateral Whether LATERAL is written
     * @param AliasMark $mark What is written before the alias
     */
    public function __construct(public readonly Query $query, public readonly ?Name $alias = null, array $columns = [], public readonly bool $lateral = false, public readonly AliasMark $mark = AliasMark::As)
    {
        Check::input($alias !== null || $mark === AliasMark::As, 'A relation without alias has no alias mark.');
        $this->columns = Check::listOf($columns, Name::class, 'The column list of a derived table holds names.');
    }

    /**
     * Derives the query and the row shape of the derived table.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        if ($this->alias === null) {
            $derivation->report(new Misuse(MisuseRule::DerivedWithoutAlias));
        }

        return new RelationFact((new DerivedShapes())->shape($derivation->query($this->query, $environment), $this->columns, $derivation));
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
        if ($this->alias !== null) {
            $this->mark->write($out);
            $out->name($this->alias, NameUse::Alias);
        }
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
