<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Utility;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Statement;

/**
 * Derives the rows a SHOW returns and the condition of its WHERE clause.
 *
 * Rule: MYSQL-SHOW-FACTS-001. A SHOW that accepts LIKE or WHERE is the
 * relation occurrence of its own result rows: its relation fact is the
 * shape of MYSQL-SHOW-ROWS-001, and the WHERE condition is derived at a
 * position whose only visible relation is that occurrence, so a column name
 * resolves to a result column (MYSQL-COLUMN-LOOKUP-001) and an unknown one
 * is a diagnostic. The output of the statement is the same shape. A column
 * name the server computes from the current database, such as
 * `Tables_in_db`, is known when the statement or the context names the
 * database; otherwise the shape is open and depends on the session's
 * current database. The operands of a LIMIT are derived at a position that
 * sees no relation. Terminates: one pass over a fixed shape.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/extended-show.html,
 * https://dev.mysql.com/doc/refman/8.4/en/show.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ShowFacts
{
    /**
     * Derives a SHOW that is the relation its filter sees, and records its rows as the output.
     */
    public function derive(Derivation $derivation, Statement&Relation $show, ShowLike|ShowWhere|null $filter): void
    {
        $fact = $derivation->relation($show, $derivation->environment());
        if ($filter instanceof ShowWhere) {
            $derivation->scalar($filter->condition, new Environment($derivation->context, null, [new VisibleRelation($show, $fact->shape)]));
        }
        $derivation->output($this->query($fact->shape, $derivation->context->columnNames));
    }

    /**
     * Records the rows of a layout as the output of a statement without filter.
     */
    public function rows(Derivation $derivation, Report $report): void
    {
        $derivation->output($this->query($this->shape($derivation, $report), $derivation->context->columnNames));
    }

    /**
     * Answers the relation fact of a layout, with the name of its first column replaced when given.
     */
    public function fact(Derivation $derivation, Report $report, ?string $first = null): RelationFact
    {
        return new RelationFact($this->shape($derivation, $report, $first));
    }

    /**
     * Answers the shape of a layout in the release of the context, with the name of its first column replaced when given.
     */
    public function shape(Derivation $derivation, Report $report, ?string $first = null): RowShape
    {
        $rows = new ShowRows();
        $columns = $rows->columns($report, $derivation->context->profile->grammar);
        if ($first !== null) {
            $columns[0] = [$first, $columns[0][1]];
        }

        return new RowShape($rows->slots($columns));
    }

    /**
     * Answers an open shape whose columns depend on a missing input.
     */
    public function open(MissingInput $missing): RelationFact
    {
        return new RelationFact(new RowShape([], [$missing]));
    }

    /**
     * Answers the output of a shape: its fields, or an undetermined projection when the shape is open.
     */
    public function query(RowShape $shape, Comparison $names): QueryFact
    {
        if (!$shape->complete()) {
            return new QueryFact([new OpenStar($shape->missing)], $names);
        }
        $fields = [];
        foreach ($shape->slots as $position => $slot) {
            $fields[] = new Field($position, $slot);
        }

        return new QueryFact($fields, $names);
    }

    /**
     * Answers the database a statement reports on: the one it names, else the current database of the context, else null.
     */
    public function database(Derivation $derivation, ?Name $written): ?string
    {
        if ($written !== null) {
            return $written->value;
        }
        return (new SessionDatabase())->named($derivation->context)?->value;
    }

    /**
     * Answers the missing input of a name the server computes from the current database.
     */
    public function currentDatabase(): SessionState
    {
        return new SessionState('the current database');
    }

    /**
     * Derives the operands of a LIMIT clause.
     */
    public function limit(Derivation $derivation, ?Limit $limit): void
    {
        if ($limit === null) {
            return;
        }
        Check::invariant($limit instanceof RowLimit, 'A SHOW limit is a row limit.');
        $derivation->scalar($limit->count, $derivation->environment());
        if ($limit->offset !== null) {
            $derivation->scalar($limit->offset, $derivation->environment());
        }
    }
}
