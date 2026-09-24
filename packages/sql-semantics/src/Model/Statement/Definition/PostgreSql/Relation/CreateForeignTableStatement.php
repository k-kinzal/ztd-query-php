<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Relation;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Definition\Relation\Foreign\ColumnForeignOptions;
use SqlSemantics\Model\Definition\Relation\Foreign\TableTemplate;
use SqlSemantics\Model\Definition\TableDeclaration;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Declares a foreign table from its own columns, template clauses, and parents, served by a foreign server.
 * @visibility public
 * @example Reading the declaration
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE FOREIGN TABLE IF NOT EXISTS app.ft (a integer NOT NULL, CHECK (a > 0)) INHERITS (p) SERVER remote OPTIONS (schema_name 'x')");
 *     $statement->definition->table->name // => 'ft'
 *     [$statement->server, $statement->ifNotExists, $statement->inherits[0]->parts, $statement->options[0]->name] // => ['remote', true, ['p'], 'schema_name']
 * @example Rejecting an empty server name
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE FOREIGN TABLE ft () SERVER remote');
 *     $statement->withServer(''); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateForeignTableStatement extends BoundStatement
{
    /**
     * @param list<ForeignOption> $options
     * @param list<QualifiedName> $inherits
     * @param list<TableTemplate> $templates
     * @param list<ColumnForeignOptions> $columnOptions
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly TableDeclaration $definition,
        public readonly string $server,
        public readonly array $options = [],
        public readonly array $inherits = [],
        public readonly array $templates = [],
        public readonly array $columnOptions = [],
        public readonly bool $ifNotExists = false,
    ) {
        RelationInvariant::dialect($origin);
        CatalogInvariant::identifier($server);
        foreach ($definition->table->columns as $column) {
            if ($column->type->dialect !== Dialect::PostgreSql) {
                throw new InvalidStructure('Foreign table columns require PostgreSQL type declarations.');
            }
        }
        Collections::objects($options, ForeignOption::class);
        Collections::objects($inherits, QualifiedName::class);
        Collections::objects($templates, TableTemplate::class);
        Collections::objects($columnOptions, ColumnForeignOptions::class);
        $names = array_map(static fn ($column): string => $column->name, $definition->table->columns);
        foreach ($columnOptions as $columnOption) {
            if (!in_array($columnOption->column, $names, true)) {
                throw new InvalidStructure('Column options must name a declared column.');
            }
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->definition, $this->server, $this->options, $this->inherits, $this->templates, $this->columnOptions, $this->ifNotExists);
    }

    /**
     * Replaces the foreign server.
     */
    public function withServer(string $server): self
    {
        return $this->changed(new self($this->origin, $this->definition, $server, $this->options, $this->inherits, $this->templates, $this->columnOptions, $this->ifNotExists));
    }

    /**
     * Replaces the table-level wrapper options.
     * @param list<ForeignOption> $options
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->definition, $this->server, $options, $this->inherits, $this->templates, $this->columnOptions, $this->ifNotExists));
    }

    /**
     * Replaces the parent tables.
     * @param list<QualifiedName> $inherits
     */
    public function withInherits(array $inherits): self
    {
        return $this->changed(new self($this->origin, $this->definition, $this->server, $this->options, $inherits, $this->templates, $this->columnOptions, $this->ifNotExists));
    }

    /**
     * Replaces the tolerance for an existing table.
     */
    public function withIfNotExists(bool $ifNotExists): self
    {
        return $this->changed(new self($this->origin, $this->definition, $this->server, $this->options, $this->inherits, $this->templates, $this->columnOptions, $ifNotExists));
    }
}
