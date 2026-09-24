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
 * Creates a text search dictionary from a template and the options that template interprets.
 * @visibility public
 * @example Creating a dictionary
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE TEXT SEARCH DICTIONARY app.stem (TEMPLATE = snowball, Language = english, StopWords = 'english')");
 *     $statement->template->parts // => ['snowball']
 *     $statement->toString() // => 'CREATE TEXT SEARCH DICTIONARY "app"."stem"(TEMPLATE = "snowball", "language" = \'english\', "stopwords" = \'english\')'
 */
final class CreateTextSearchDictionaryStatement extends BoundStatement
{
    /**
     * @param list<DictionaryOption> $options
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly QualifiedName $template, public readonly array $options = [])
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($name);
        TypeSystemInvariant::name($template);
        Collections::objects($options, DictionaryOption::class);
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
        return new self($origin, $this->name, $this->template, $this->options);
    }

    /**
     * Replaces the dictionary name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->template, $this->options));
    }

    /**
     * Replaces the template.
     */
    public function withTemplate(QualifiedName $template): self
    {
        return $this->changed(new self($this->origin, $this->name, $template, $this->options));
    }

    /**
     * Replaces the template options.
     * @param list<DictionaryOption> $options
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->template, $options));
    }
}
