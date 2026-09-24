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
 * Creates an empty text search configuration for a parser.
 * @visibility public
 * @example Creating a configuration
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH CONFIGURATION app.english (PARSER = pg_catalog.default)');
 *     $statement->parser->parts // => ['pg_catalog', 'default']
 */
final class CreateTextSearchConfigurationStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly QualifiedName $parser)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($name);
        TypeSystemInvariant::name($parser);
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
        return new self($origin, $this->name, $this->parser);
    }

    /**
     * Replaces the configuration name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->parser));
    }

    /**
     * Replaces the parser.
     */
    public function withParser(QualifiedName $parser): self
    {
        return $this->changed(new self($this->origin, $this->name, $parser));
    }
}
