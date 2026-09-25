<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\TextSearch;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\TextSearch\DictionaryOption;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Sets or removes template options of a text search dictionary; an option without an argument is removed.
 * @visibility public
 * @example Changing the stop words and removing an option
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH DICTIONARY app.stem (StopWords = russian, Accept)');
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'ALTER TEXT SEARCH DICTIONARY "app"."stem"("stopwords" = \'russian\', "accept")'
 */
final class AlterTextSearchDictionaryStatement extends BoundStatement
{
    /**
     * @param non-empty-list<DictionaryOption> $options
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $dictionary, public readonly array $options)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($dictionary);
        Collections::objects(Collections::nonEmpty($options), DictionaryOption::class);
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
        return new self($origin, $this->dictionary, $this->options);
    }

    /**
     * Replaces the altered dictionary.
     */
    public function withDictionary(QualifiedName $dictionary): self
    {
        return $this->changed(new self($this->origin, $dictionary, $this->options));
    }

    /**
     * Replaces the changed options.
     * @param non-empty-list<DictionaryOption> $options
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->dictionary, $options));
    }
}
