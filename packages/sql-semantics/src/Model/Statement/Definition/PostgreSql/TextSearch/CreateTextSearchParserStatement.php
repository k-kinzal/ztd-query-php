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
 * Creates a text search parser from its start, token, end, and lexeme-type functions and an optional headline function.
 * @visibility public
 * @example Creating a parser
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH PARSER app.words (START = prsd_start, GETTOKEN = prsd_nexttoken, END = prsd_end, LEXTYPES = prsd_lextype)');
 *     $statement->lextypes->parts // => ['prsd_lextype']
 *     $statement->headline // => null
 */
final class CreateTextSearchParserStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly QualifiedName $start, public readonly QualifiedName $gettoken, public readonly QualifiedName $end, public readonly QualifiedName $lextypes, public readonly ?QualifiedName $headline = null)
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
        return new self($origin, $this->name, $this->start, $this->gettoken, $this->end, $this->lextypes, $this->headline);
    }

    /**
     * Replaces the parser name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->start, $this->gettoken, $this->end, $this->lextypes, $this->headline));
    }

    /**
     * Replaces the start function.
     */
    public function withStart(QualifiedName $start): self
    {
        return $this->changed(new self($this->origin, $this->name, $start, $this->gettoken, $this->end, $this->lextypes, $this->headline));
    }

    /**
     * Replaces the next-token function.
     */
    public function withGettoken(QualifiedName $gettoken): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->start, $gettoken, $this->end, $this->lextypes, $this->headline));
    }

    /**
     * Replaces the end function.
     */
    public function withEnd(QualifiedName $end): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->start, $this->gettoken, $end, $this->lextypes, $this->headline));
    }

    /**
     * Replaces the lexeme-type function.
     */
    public function withLextypes(QualifiedName $lextypes): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->start, $this->gettoken, $this->end, $lextypes, $this->headline));
    }

    /**
     * Replaces or removes the headline function.
     */
    public function withHeadline(?QualifiedName $headline): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->start, $this->gettoken, $this->end, $this->lextypes, $headline));
    }
}
