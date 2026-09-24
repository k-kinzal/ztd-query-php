<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\TextSearch;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\TextSearch\MappingChange;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Maps token types of a text search configuration to an ordered dictionary list, adding mappings or replacing existing ones.
 * @visibility public
 * @example Replacing the mapping of two token types
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION app.english ALTER MAPPING FOR word, hword WITH app.synonyms, english_stem');
 *     $statement->change // => \SqlSemantics\Model\Definition\TypeSystem\TextSearch\MappingChange::Alter
 *     $statement->tokenTypes // => ['word', 'hword']
 *     $statement->toString() // => 'ALTER TEXT SEARCH CONFIGURATION "app"."english" ALTER MAPPING FOR "word", "hword" WITH "app"."synonyms", "english_stem"'
 */
final class MapTextSearchTokensStatement extends BoundStatement
{
    /**
     * @param non-empty-list<string> $tokenTypes
     * @param non-empty-list<QualifiedName> $dictionaries
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $configuration, public readonly MappingChange $change, public readonly array $tokenTypes, public readonly array $dictionaries)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($configuration);
        TextSearchInvariant::tokenTypes($tokenTypes);
        Collections::objects(Collections::nonEmpty($dictionaries), QualifiedName::class);
        foreach ($dictionaries as $dictionary) {
            TypeSystemInvariant::name($dictionary);
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
        return new self($origin, $this->configuration, $this->change, $this->tokenTypes, $this->dictionaries);
    }

    /**
     * Replaces the altered configuration.
     */
    public function withConfiguration(QualifiedName $configuration): self
    {
        return $this->changed(new self($this->origin, $configuration, $this->change, $this->tokenTypes, $this->dictionaries));
    }

    /**
     * Chooses between adding and replacing mappings.
     */
    public function withChange(MappingChange $change): self
    {
        return $this->changed(new self($this->origin, $this->configuration, $change, $this->tokenTypes, $this->dictionaries));
    }

    /**
     * Replaces the mapped token types.
     * @param non-empty-list<string> $tokenTypes
     */
    public function withTokenTypes(array $tokenTypes): self
    {
        return $this->changed(new self($this->origin, $this->configuration, $this->change, $tokenTypes, $this->dictionaries));
    }

    /**
     * Replaces the ordered dictionaries.
     * @param non-empty-list<QualifiedName> $dictionaries
     */
    public function withDictionaries(array $dictionaries): self
    {
        return $this->changed(new self($this->origin, $this->configuration, $this->change, $this->tokenTypes, $dictionaries));
    }
}
