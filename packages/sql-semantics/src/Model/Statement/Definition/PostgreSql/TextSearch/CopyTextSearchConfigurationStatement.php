<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\TextSearch;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates a text search configuration with the parser and mappings of an existing configuration.
 * @visibility public
 * @example Copying a configuration
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH CONFIGURATION app.english (COPY = pg_catalog.english)');
 *     $statement->copied->parts // => ['pg_catalog', 'english']
 *     $statement->toString() // => 'CREATE TEXT SEARCH CONFIGURATION "app"."english"(COPY = "pg_catalog"."english")'
 */
final class CopyTextSearchConfigurationStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly QualifiedName $copied)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($name);
        TypeSystemInvariant::name($copied);
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
        return new self($origin, $this->name, $this->copied);
    }

    /**
     * Replaces the configuration name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->copied));
    }

    /**
     * Replaces the copied configuration.
     */
    public function withCopied(QualifiedName $copied): self
    {
        return $this->changed(new self($this->origin, $this->name, $copied));
    }
}
