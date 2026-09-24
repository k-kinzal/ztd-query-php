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
 * Changes WITH options of a publication; unspecified options keep their values.
 * @visibility public
 * @example Reading changed publication options
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind("ALTER PUBLICATION pub SET (publish = 'update, delete')");
 *     $statement->options->publish // => [\SqlSemantics\Model\Definition\Replication\Publication\PublishedOperation::Update, \SqlSemantics\Model\Definition\Replication\Publication\PublishedOperation::Delete]
 *     $statement->options->viaPartitionRoot // => null
 */
final class AlterPublicationOptionsStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly PublicationOptions $options,
    ) {
        PublicationInvariant::identity($origin, $name);
        if ($options->isEmpty()) {
            throw new InvalidStructure('ALTER PUBLICATION SET changes at least one option.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
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
     * Replaces the changed options, at least one.
     */
    public function withOptions(PublicationOptions $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $options));
    }
}
