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
 * Creates a text search template from its lexize function and an optional init function.
 * @visibility public
 * @example Creating a template
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH TEMPLATE app.plain (INIT = dsimple_init, LEXIZE = dsimple_lexize)');
 *     $statement->lexize->parts // => ['dsimple_lexize']
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'CREATE TEXT SEARCH TEMPLATE "app"."plain"(INIT = "dsimple_init", LEXIZE = "dsimple_lexize")'
 */
final class CreateTextSearchTemplateStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly QualifiedName $lexize, public readonly ?QualifiedName $init = null)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($name);
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
        return new self($origin, $this->name, $this->lexize, $this->init);
    }

    /**
     * Replaces the template name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->lexize, $this->init));
    }

    /**
     * Replaces the lexize function.
     */
    public function withLexize(QualifiedName $lexize): self
    {
        return $this->changed(new self($this->origin, $this->name, $lexize, $this->init));
    }

    /**
     * Replaces or removes the init function.
     */
    public function withInit(?QualifiedName $init): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->lexize, $init));
    }
}
