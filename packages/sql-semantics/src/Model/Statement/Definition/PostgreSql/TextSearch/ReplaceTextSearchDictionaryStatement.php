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
 * Replaces one dictionary with another in the mappings of a text search configuration, for the listed token types or, when none are listed, for every token type.
 * @visibility public
 * @example Replacing a dictionary for every token type
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION app.english ALTER MAPPING REPLACE english_stem WITH app.stem');
 *     $statement->tokenTypes // => null
 *     $statement->replacement->parts // => ['app', 'stem']
 */
final class ReplaceTextSearchDictionaryStatement extends BoundStatement
{
    /**
     * @param non-empty-list<string>|null $tokenTypes
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $configuration, public readonly QualifiedName $dictionary, public readonly QualifiedName $replacement, public readonly ?array $tokenTypes = null)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($configuration);
        TypeSystemInvariant::name($dictionary);
        TypeSystemInvariant::name($replacement);
        if ($tokenTypes !== null) {
            TextSearchInvariant::tokenTypes($tokenTypes);
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
        return new self($origin, $this->configuration, $this->dictionary, $this->replacement, $this->tokenTypes);
    }

    /**
     * Replaces the altered configuration.
     */
    public function withConfiguration(QualifiedName $configuration): self
    {
        return $this->changed(new self($this->origin, $configuration, $this->dictionary, $this->replacement, $this->tokenTypes));
    }

    /**
     * Replaces the dictionary being replaced.
     */
    public function withDictionary(QualifiedName $dictionary): self
    {
        return $this->changed(new self($this->origin, $this->configuration, $dictionary, $this->replacement, $this->tokenTypes));
    }

    /**
     * Replaces the new dictionary.
     */
    public function withReplacement(QualifiedName $replacement): self
    {
        return $this->changed(new self($this->origin, $this->configuration, $this->dictionary, $replacement, $this->tokenTypes));
    }

    /**
     * Restricts the replacement to token types, or applies it to every token type with null.
     * @param non-empty-list<string>|null $tokenTypes
     */
    public function withTokenTypes(?array $tokenTypes): self
    {
        return $this->changed(new self($this->origin, $this->configuration, $this->dictionary, $this->replacement, $tokenTypes));
    }
}
