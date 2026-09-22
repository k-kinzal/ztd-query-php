<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

/**
 * A SELECT projection over relations, with separate matching, filtering, and grouping stages.
 *
 * @example Reading the statement structure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT id FROM t');
 *     $statement->outputs[0]->name // => 'id'
 *
 * @visibility public
 */
final class BoundSelect extends BoundQuery
{
    /**
     * @param list<OutputColumn> $outputs Ordered replacement result columns
     */
    public function withOutputs(array $outputs): self
    {
        return $this->clause('outputs', Sql\Parts::outputs($outputs, $this->context()->schema()->dialect));
    }

    /**
     * Sets or removes the row predicate and validates its names and type.
     */
    public function withWhere(?Expression $where): self
    {
        return $this->clause('where', Sql\Parts::expressions('WHERE', $where === null ? [] : [$where]));
    }

    /**
     * @param list<Expression> $expressions
     */
    public function withGroupBy(array $expressions): self
    {
        return $this->clause('groupBy', Sql\Parts::expressions('GROUP BY', $expressions));
    }

    /**
     * Sets or removes the predicate evaluated after grouping.
     */
    public function withHaving(?Expression $having): self
    {
        return $this->clause('having', Sql\Parts::expressions('HAVING', $having === null ? [] : [$having]));
    }

    /**
     * Changes the complete input relation, including joins and derived queries.
     */
    public function withFrom(TableUse|Join|null $from): self
    {
        return $this->clause('from', new Sql\Tree('from', $from === null ? [] : [Sql\Build::keyword('FROM'), Sql\Source::read($from->source)]));
    }
}
