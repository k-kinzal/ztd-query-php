<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Foreign\AllForeignTables;
use SqlSemantics\Model\Definition\Foreign\ExcludeForeignTables;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Definition\Foreign\ImportOnlyTables;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Imports selected remote definitions into one local schema without contacting a server.
 * @visibility public
 * @example Inspecting the destination and selection
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('IMPORT FOREIGN SCHEMA ext LIMIT TO (users) FROM SERVER remote INTO app');
 *     $statement->localSchema // => 'app'
 *     $statement->selection instanceof \SqlSemantics\Model\Definition\Foreign\ImportOnlyTables // => true
 */
final class ImportForeignSchemaStatement extends BoundStatement
{
    /**
     * @param list<ForeignOption> $options Ordered wrapper-specific text options
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $remoteSchema,
        public readonly string $server,
        public readonly string $localSchema,
        public readonly AllForeignTables|ImportOnlyTables|ExcludeForeignTables $selection = AllForeignTables::InSchema,
        public readonly array $options = [],
    ) {
        if ($origin->dialect !== Dialect::PostgreSql || $remoteSchema === '' || $server === '' || $localSchema === '') {
            throw new InvalidStructure('Foreign schema import requires PostgreSQL and nonempty remote, server, and local names.');
        }
        Collections::objects($options, ForeignOption::class);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Import;
    }

    /**
     * Retains all import operands while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->remoteSchema, $this->server, $this->localSchema, $this->selection, $this->options);
    }

    /**
     * Replaces the remote schema and the server that interprets it together.
     */
    public function withRemote(string $server, string $remoteSchema): self
    {
        return $this->changed(new self($this->origin, $remoteSchema, $server, $this->localSchema, $this->selection, $this->options));
    }

    /**
     * Selects the local schema that will receive the imported declarations.
     */
    public function withLocalSchema(string $localSchema): self
    {
        return $this->changed(new self($this->origin, $this->remoteSchema, $this->server, $localSchema, $this->selection, $this->options));
    }

    /**
     * Changes the complete selection while preserving its required table list.
     */
    public function withSelection(AllForeignTables|ImportOnlyTables|ExcludeForeignTables $selection): self
    {
        return $this->changed(new self($this->origin, $this->remoteSchema, $this->server, $this->localSchema, $selection, $this->options));
    }

    /**
     * @param list<ForeignOption> $options Ordered wrapper-specific options
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->remoteSchema, $this->server, $this->localSchema, $this->selection, $options));
    }
}
