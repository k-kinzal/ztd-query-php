<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\DerivedTable;

/**
 * Identifies the independent query introduced by a local binding.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class LocalDefinition
{
    /**
     * Reads the independently scoped query introduced by the derived table.
     *
     * @throws InvalidConstruction When the local source is outside the independent domain
     */
    public function resolve(ParsedSelect $body): ParsedSelect|QueryExpression|ValuesList
    {
        $source = $body->from;
        Check::input($source instanceof DerivedTable, 'A local projection needs a derived source.');
        Check::input(!$source->lateral, 'The local definition cannot depend on a lateral scope.');
        $query = $source->query;
        while ($query instanceof ParenthesizedQuery) {
            $query = $query->query;
        }
        Check::input($query instanceof ParsedSelect || $query instanceof ValuesList || ($query instanceof QueryExpression && $query->with !== null), 'A local definition requires an independent projection or constant table.');

        return $query;
    }

    /**
     * Checks a projection consumer before resolving its local input.
     *
     * @throws InvalidConstruction When the root or an outer clause has no local IR representation
     */
    public function consumer(ParsedSelect|QueryExpression $statement): ParsedSelect
    {
        $body = $this->queryBlock($statement);
        Check::input($body->where === null, 'The local projection requires an ordinary result without outer clauses.');

        return $body;
    }

    /**
     * Checks the clauses shared by a local consumer and a recursive step.
     */
    public function queryBlock(ParsedSelect|QueryExpression $statement): ParsedSelect
    {
        $body = $statement instanceof QueryExpression ? $statement->body : $statement;
        Check::input($body instanceof ParsedSelect, 'A local projection requires a query with a local source.');
        Check::input(
            $body->groupBy === [] && $body->having === null
            && $body->windows === [] && $body->distinct === null && $body->into === null && $body->options === null,
            'The local projection requires an ordinary result without outer clauses.'
        );

        return $body;
    }
}
