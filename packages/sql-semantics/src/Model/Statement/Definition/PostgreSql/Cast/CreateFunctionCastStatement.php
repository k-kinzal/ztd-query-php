<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Cast;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Routine;
use SqlSemantics\Model\Definition\TypeSystem\Cast\CastContext;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Creates a cast between two types that converts through a cast function.
 * @visibility public
 * @example Creating an implicit function cast
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE CAST (integer AS app.tag) WITH FUNCTION app.to_tag(integer) AS IMPLICIT');
 *     $statement->sourceType->name // => 'integer'
 *     $statement->function->name->parts // => ['app', 'to_tag']
 *     $statement->castContext // => \SqlSemantics\Model\Definition\TypeSystem\Cast\CastContext::Implicit
 */
final class CreateFunctionCastStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly TypeDescriptor $sourceType, public readonly TypeDescriptor $targetType, public readonly Routine\RoutineByName|Routine\RoutineBySignature $function, public readonly CastContext $castContext = CastContext::Explicit)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::type($sourceType);
        TypeSystemInvariant::type($targetType);
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
        return new self($origin, $this->sourceType, $this->targetType, $this->function, $this->castContext);
    }

    /**
     * Replaces the source type.
     */
    public function withSourceType(TypeDescriptor $sourceType): self
    {
        return $this->changed(new self($this->origin, $sourceType, $this->targetType, $this->function, $this->castContext));
    }

    /**
     * Replaces the target type.
     */
    public function withTargetType(TypeDescriptor $targetType): self
    {
        return $this->changed(new self($this->origin, $this->sourceType, $targetType, $this->function, $this->castContext));
    }

    /**
     * Replaces the cast function.
     */
    public function withFunction(Routine\RoutineByName|Routine\RoutineBySignature $function): self
    {
        return $this->changed(new self($this->origin, $this->sourceType, $this->targetType, $function, $this->castContext));
    }

    /**
     * Replaces the cast context.
     */
    public function withCastContext(CastContext $castContext): self
    {
        return $this->changed(new self($this->origin, $this->sourceType, $this->targetType, $this->function, $castContext));
    }
}
