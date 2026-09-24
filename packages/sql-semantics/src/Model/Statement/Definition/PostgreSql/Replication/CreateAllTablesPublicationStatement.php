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
 * Creates a publication of every table in the database, including tables created later.
 * @visibility public
 * @example Reading a publication of all tables
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind("CREATE PUBLICATION pub FOR ALL TABLES WITH (publish = 'insert')");
 *     $statement->options->publish // => [\SqlSemantics\Model\Definition\Replication\Publication\PublishedOperation::Insert]
 *     $statement->toString() // => 'CREATE PUBLICATION "pub" FOR ALL TABLES WITH (publish = \'insert\')'
 */
final class CreateAllTablesPublicationStatement extends BoundStatement
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
