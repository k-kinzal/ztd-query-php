<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Table;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\StatementOperands;

/**
 * Creates a MySQL table by copying the definition of a required template table.
 * @visibility public
 * @example Reading the copied column declaration
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE original(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE TABLE copied LIKE original');
 *     $statement->template->declaration->columns[0]->name // => 'id'
 */
final class CreateTableLikeStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly QualifiedName $target,
        public readonly TableReference $template,
        public readonly bool $temporary = false,
        public readonly bool $ifNotExists = false,
    ) {
        if ($origin->dialect !== Dialect::MySql || count($target->parts) > 2 || $template->alias !== null) {
            throw new InvalidStructure('CREATE TABLE LIKE requires a MySQL table name and an unaliased template.');
        }
        StatementOperands::relation($template, Dialect::MySql);
        parent::__construct($origin);
    }

    /**
     * Returns the schema-creation category.
     */
    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Preserves the copy operation when replacing diagnostic provenance.
     * @visibility SqlSemantics
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->target, $this->template, $this->temporary, $this->ifNotExists);
    }

    /**
     * Changes the destination without changing the source schema snapshot.
     */
    public function withTarget(QualifiedName $target): self
    {
        return $this->changed(new self($this->origin, $target, $this->template, $this->temporary, $this->ifNotExists));
    }

    /**
     * Replaces the required table whose definition is copied.
     */
    public function withTemplate(TableReference $template): self
    {
        return $this->changed(new self($this->origin, $this->target, $template, $this->temporary, $this->ifNotExists));
    }
}
