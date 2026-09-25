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
 * Removes the mappings of token types from a text search configuration.
 * @visibility public
 * @example Dropping mappings when they exist
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION app.english DROP MAPPING IF EXISTS FOR email, url');
 *     $statement->ifExists // => true
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'ALTER TEXT SEARCH CONFIGURATION "app"."english" DROP MAPPING IF EXISTS FOR "email", "url"'
 */
final class DropTextSearchMappingStatement extends BoundStatement
{
    /**
     * @param non-empty-list<string> $tokenTypes
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $configuration, public readonly array $tokenTypes, public readonly bool $ifExists = false)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($configuration);
        TextSearchInvariant::tokenTypes($tokenTypes);
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
        return new self($origin, $this->configuration, $this->tokenTypes, $this->ifExists);
    }

    /**
     * Replaces the altered configuration.
     */
    public function withConfiguration(QualifiedName $configuration): self
    {
        return $this->changed(new self($this->origin, $configuration, $this->tokenTypes, $this->ifExists));
    }

    /**
     * Replaces the unmapped token types.
     * @param non-empty-list<string> $tokenTypes
     */
    public function withTokenTypes(array $tokenTypes): self
    {
        return $this->changed(new self($this->origin, $this->configuration, $tokenTypes, $this->ifExists));
    }

    /**
     * Replaces the tolerance for missing mappings.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->configuration, $this->tokenTypes, $ifExists));
    }
}
