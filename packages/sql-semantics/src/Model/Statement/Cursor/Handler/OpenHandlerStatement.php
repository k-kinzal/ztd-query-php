<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Cursor\Handler;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\StatementOperands;

/**
 * Opens a table for direct HANDLER access; the handler is named by the alias, or by the table name without one.
 * @visibility public
 * @example Opening a handler under an alias
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('HANDLER t OPEN AS h');
 *     [$statement->table->declaration->name, $statement->handler()] // => ['t', 'h']
 */
final class OpenHandlerStatement extends BoundStatement
{
    /**
     * @param TableReference $table Opened table with the optional handler alias
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly TableReference $table)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('HANDLER requires MySQL.');
        }
        StatementOperands::relation($table, $origin->dialect);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Handler;
    }

    /**
     * Returns the name later HANDLER operations use: the alias, or the table name.
     */
    public function handler(): string
    {
        return $this->table->alias ?? $this->table->name->parts[count($this->table->name->parts) - 1];
    }

    /**
     * Retains the opened table while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->table);
    }

    /**
     * Opens another table or alias.
     */
    public function withTable(TableReference $table): self
    {
        return $this->changed(new self($this->origin, $table));
    }
}
