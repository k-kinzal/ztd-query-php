<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Stores or removes the comment of one catalog object; a null comment removes it.
 * @visibility public
 * @example Reading a table comment request
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("COMMENT ON TABLE app.users IS 'people'");
 *     $statement->object->kind->value // => 'TABLE'
 *     $statement->comment->text // => "'people'"
 * @example Removing a comment
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('COMMENT ON SCHEMA app IS NULL');
 *     $statement->comment // => null
 */
final class CommentOnStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly ObjectAddress $object, public readonly ?Literal $comment)
    {
        CatalogInvariant::dialect($origin);
        CatalogInvariant::comment($object);
        CatalogInvariant::text($comment);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Comment;
    }

    /**
     * Retains the object and text while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->object, $this->comment);
    }

    /**
     * Replaces the commented object in a separately validated statement.
     */
    public function withObject(ObjectAddress $object): self
    {
        return $this->changed(new self($this->origin, $object, $this->comment));
    }

    /**
     * Replaces the stored text; null requests removal of the comment.
     */
    public function withComment(?Literal $comment): self
    {
        return $this->changed(new self($this->origin, $this->object, $comment));
    }
}
