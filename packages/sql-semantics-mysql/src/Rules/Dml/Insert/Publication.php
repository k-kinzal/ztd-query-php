<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml\Insert;

use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\DestinationColumn;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertStatement;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertTarget;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesClause;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Source\SourceMap;
use SqlSemantics\Statement\Source\Span;
use SqlSemantics\Statement\Statement;
use SqlSemantics\Statement\Type\Known;

/**
 * Publishes the first migrated INSERT form with meaning owned by its SQL values.
 *
 * Binding indexes are temporary analysis data. No environment, index, SQL text or
 * parser node is retained by the returned statement.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Publication
{
    /**
     * Identifies the VALUES character-literal form migrated to local semantics.
     * Other forms remain explicit migration work; they are not excluded from fuzz.
     */
    public function supports(Statement $statement): bool
    {
        if (!$statement instanceof InsertRows || $statement->into->replace || $statement->into->ignore || $statement->into->priority !== null || $statement->into->hints !== [] || $statement->into->table->partitions !== [] || $statement->alias !== null || $statement->onDuplicate !== []) {
            return false;
        }
        foreach ($statement->into->columns->columns ?? [] as $column) {
            if (!$column instanceof ColumnUse || $column->qualifier !== null) {
                return false;
            }
        }
        foreach ($statement->rows as $row) {
            foreach ($row->values as $value) {
                if (!$value instanceof StringLiteral || $value->introducer !== null || $value->national) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Places types, declaration identities and source locations on their owners.
     */
    public function publish(InsertRows $statement, AnalysisContext $context, Facts $facts, SourceMap $sources): InsertStatement
    {
        Check::input($this->supports($statement), 'The INSERT form must have a local semantic publication rule.');
        $resolution = $facts->relation($statement->into->table)->table;
        Check::invariant($resolution !== null, 'An insertion target has a table resolution.');
        $columns = null;
        if ($statement->into->columns !== null) {
            $columns = [];
            foreach ($statement->into->columns->columns as $column) {
                Check::invariant($column instanceof ColumnUse, 'A migrated destination is a column name.');
                $binding = $facts->scalar($column)->resolution;
                $columns[] = new DestinationColumn($column->name, $resolution, $binding instanceof ResolvedColumn ? $binding->declaration() : null, $this->span($column, $sources));
            }
        }
        $rows = [];
        foreach ($statement->rows as $row) {
            $values = [];
            foreach ($row->values as $value) {
                Check::invariant($value instanceof StringLiteral, 'A migrated VALUES element is a string literal.');
                $type = $facts->scalar($value)->type;
                Check::invariant($type instanceof Known && $type->descriptor instanceof Domain, 'A character literal has a resolved MySQL type.');
                $values[] = new StringLiteral($value->segments, $value->introducer, $value->national, $type->descriptor->collation, $this->span($value, $sources));
            }
            $rows[] = $values;
        }
        return new InsertStatement(
            $context->profile,
            new InsertTarget($statement->into->table->name, $resolution, $columns, $this->span($statement->into->table, $sources)),
            new ValuesClause($rows, $statement->valuesSource, $statement->keyword),
            Settings::of($context)->client,
            $this->span($statement, $sources),
            $statement->into->intoWritten,
        );
    }

    /**
     * Copies a location, never text, out of the temporary lowering index.
     */
    public function span(Node $node, SourceMap $sources): ?Span
    {
        $origin = $sources->of($node);
        return $origin === null ? null : new Span($origin->offset, $origin->length);
    }
}
