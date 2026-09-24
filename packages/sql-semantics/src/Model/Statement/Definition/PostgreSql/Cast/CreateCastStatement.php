<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Cast;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\Cast\CastContext;
use SqlSemantics\Model\Definition\TypeSystem\Cast\CastMechanism;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Identity\ArrayStorage;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Creates a cast between two different types that needs no cast function: binary-coercible or through text I/O.
 * @visibility public
 * @example Creating an I/O conversion cast
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE CAST (bigint AS money) WITH INOUT AS ASSIGNMENT');
 *     $statement->mechanism // => \SqlSemantics\Model\Definition\TypeSystem\Cast\CastMechanism::InOut
 *     $statement->toString() // => 'CREATE CAST(bigint AS "money") WITH INOUT AS ASSIGNMENT'
 * @example Rejecting a cast from a type to itself
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE CAST (bigint AS money) WITH INOUT');
 *     $statement->withTargetType($statement->sourceType); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateCastStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly TypeDescriptor $sourceType, public readonly TypeDescriptor $targetType, public readonly CastMechanism $mechanism, public readonly CastContext $castContext = CastContext::Explicit)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::type($sourceType);
        TypeSystemInvariant::type($targetType);
        CreateCastStatement::types($sourceType, $targetType, $mechanism);
        parent::__construct($origin);
    }

    /**
     * Without a function the source and target differ, and a binary-coercible cast joins no array types.
     * @throws InvalidStructure
     */
    public static function types(TypeDescriptor $sourceType, TypeDescriptor $targetType, CastMechanism $mechanism): void
    {
        if ($sourceType->name === $targetType->name) {
            throw new InvalidStructure('A cast without a function requires two different types.');
        }
        if ($mechanism === CastMechanism::BinaryCoercible && ($sourceType->identity instanceof ArrayStorage || $targetType->identity instanceof ArrayStorage)) {
            throw new InvalidStructure('Array types are not binary-coercible.');
        }
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
        return new self($origin, $this->sourceType, $this->targetType, $this->mechanism, $this->castContext);
    }

    /**
     * Replaces the source type.
     */
    public function withSourceType(TypeDescriptor $sourceType): self
    {
        return $this->changed(new self($this->origin, $sourceType, $this->targetType, $this->mechanism, $this->castContext));
    }

    /**
     * Replaces the target type.
     */
    public function withTargetType(TypeDescriptor $targetType): self
    {
        return $this->changed(new self($this->origin, $this->sourceType, $targetType, $this->mechanism, $this->castContext));
    }

    /**
     * Replaces the conversion mechanism.
     */
    public function withMechanism(CastMechanism $mechanism): self
    {
        return $this->changed(new self($this->origin, $this->sourceType, $this->targetType, $mechanism, $this->castContext));
    }

    /**
     * Replaces the cast context.
     */
    public function withCastContext(CastContext $castContext): self
    {
        return $this->changed(new self($this->origin, $this->sourceType, $this->targetType, $this->mechanism, $castContext));
    }
}
