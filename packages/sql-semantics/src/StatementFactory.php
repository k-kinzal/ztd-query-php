<?php

declare(strict_types=1);

namespace SqlSemantics;

use SqlSemantics\Binding\Editing\StatementContext;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Sql;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\TableDefinition;

/**
 * Constructs and validates statements from structure against an immutable schema.
 *
 * @example Constructing a constant projection
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $value = \SqlSemantics\Model\Expression::literal(42, $schema->dialect);
 *     $statement = (new \SqlSemantics\StatementFactory($schema))->select([new \SqlSemantics\Model\OutputColumn(0, 'answer', $value)]);
 *     $statement->outputs[0]->expression->type->name // => 'integer'
 *
 * @visibility public
 */
final class StatementFactory
{
    /**
     * Uses the same schema snapshot and grammar release as Binder.
     */
    public function __construct(public readonly Schema $schema)
    {
    }

    /**
     * Constructs any statement form from a complete SQL structure and validates it.
     */
    public function create(Sql\Tree $structure): BoundStatement
    {
        return (new StatementContext($this->schema))->bind($structure);
    }

    /**
     * @param list<OutputColumn> $outputs Ordered projected values
     * @throws InvalidStructure
     */
    public function select(array $outputs, ?TableDefinition $from = null, ?Expression $where = null): BoundSelect
    {
        $parts = [Sql\Build::keyword('SELECT'), Sql\Parts::outputs($outputs, $this->schema->dialect)];
        if ($from !== null) {
            $parts[] = new Sql\Tree('from', [Sql\Build::keyword('FROM'), Sql\Build::identifier($from->schema === '' ? [$from->name] : [$from->schema, $from->name], $this->schema->dialect)]);
        }
        $parts[] = Sql\Parts::expressions('WHERE', $where === null ? [] : [$where]);
        $statement = $this->create(new Sql\Tree('select', $parts));
        if (!$statement instanceof BoundSelect) {
            throw new InvalidStructure('A projection must produce a SELECT statement.');
        }
        return $statement;
    }
}
