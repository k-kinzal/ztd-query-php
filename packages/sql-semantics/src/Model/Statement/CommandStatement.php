<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use SqlSemantics\Model\BoundStatement;

/**
 * A language command retaining its complete structure and nested operations.
 *
 * @example Reading the statement structure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('EXPLAIN SELECT id FROM t');
 *     $statement->statements[0]->outputs[0]->name // => 'id'
 *
 * @visibility public
 */
final class CommandStatement extends BoundStatement
{
    /**
     * Replaces an owned nested command, retaining its enclosing command options.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function withStatement(BoundStatement $target, BoundStatement $replacement): self
    {
        if (!in_array($target, $this->statements, true)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('The nested statement does not belong to this command.');
        }
        return $this->component($target->source, $replacement->sql);
    }
}
