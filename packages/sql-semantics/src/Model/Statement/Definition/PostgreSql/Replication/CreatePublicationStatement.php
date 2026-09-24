<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Replication;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Replication\Publication\PublicationInvariant;
use SqlSemantics\Model\Definition\Replication\Publication\PublicationOptions;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates a publication that publishes no table until objects are added.
 * @visibility public
 * @example Reading an empty publication
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE PUBLICATION pub WITH (publish_via_partition_root = off)');
 *     $statement->options->viaPartitionRoot // => false
 *     $statement->toString() // => 'CREATE PUBLICATION "pub" WITH (publish_via_partition_root = false)'
 */
final class CreatePublicationStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly PublicationOptions $options = new PublicationOptions(),
    ) {
        PublicationInvariant::identity($origin, $name);
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
        return new self($origin, $this->name, $this->options);
    }

    /**
     * Replaces the publication name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->options));
    }

    /**
     * Replaces the WITH options.
     */
    public function withOptions(PublicationOptions $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $options));
    }
}
