<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Cast;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\TransformIdentity;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Drops the transform of a type for a procedural language.
 * @visibility public
 * @example Dropping a transform
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP TRANSFORM IF EXISTS FOR hstore LANGUAGE plpython3u CASCADE');
 *     $statement->transform->type->name // => 'hstore'
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'DROP TRANSFORM IF EXISTS FOR "hstore" LANGUAGE "plpython3u" CASCADE'
 */
final class DropTransformStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly TransformIdentity $transform, public readonly bool $ifExists = false, public readonly DropBehavior $behavior = DropBehavior::Default)
    {
        TypeSystemInvariant::dialect($origin);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Drop;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->transform, $this->ifExists, $this->behavior);
    }

    /**
     * Replaces the dropped transform.
     */
    public function withTransform(TransformIdentity $transform): self
    {
        return $this->changed(new self($this->origin, $transform, $this->ifExists, $this->behavior));
    }

    /**
     * Replaces the tolerance for a missing transform.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->transform, $ifExists, $this->behavior));
    }

    /**
     * Replaces the dependent-object policy.
     */
    public function withBehavior(DropBehavior $behavior): self
    {
        return $this->changed(new self($this->origin, $this->transform, $this->ifExists, $behavior));
    }
}
