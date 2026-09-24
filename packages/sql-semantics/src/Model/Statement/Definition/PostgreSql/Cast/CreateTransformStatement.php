<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Cast;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Routine;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Creates or replaces the transform of a type for a procedural language, with a FROM SQL function, a TO SQL function, or both.
 * @visibility public
 * @example Creating a transform
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE OR REPLACE TRANSFORM FOR hstore LANGUAGE plpython3u (FROM SQL WITH FUNCTION hstore_to_plpython(internal))');
 *     $statement->language // => 'plpython3u'
 *     $statement->fromSql->name->parts // => ['hstore_to_plpython']
 *     $statement->toSql // => null
 *     $statement->orReplace // => true
 */
final class CreateTransformStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly TypeDescriptor $type, public readonly string $language, public readonly Routine\RoutineByName|Routine\RoutineBySignature|null $fromSql, public readonly Routine\RoutineByName|Routine\RoutineBySignature|null $toSql, public readonly bool $orReplace = false)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::type($type);
        TypeSystemInvariant::identifier($language);
        if ($fromSql === null && $toSql === null) {
            throw new InvalidStructure('A transform requires a FROM SQL function, a TO SQL function, or both.');
        }
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
        return new self($origin, $this->type, $this->language, $this->fromSql, $this->toSql, $this->orReplace);
    }

    /**
     * Replaces the transformed type.
     */
    public function withType(TypeDescriptor $type): self
    {
        return $this->changed(new self($this->origin, $type, $this->language, $this->fromSql, $this->toSql, $this->orReplace));
    }

    /**
     * Replaces the procedural language.
     */
    public function withLanguage(string $language): self
    {
        return $this->changed(new self($this->origin, $this->type, $language, $this->fromSql, $this->toSql, $this->orReplace));
    }

    /**
     * Replaces or removes the FROM SQL function.
     */
    public function withFromSql(Routine\RoutineByName|Routine\RoutineBySignature|null $fromSql): self
    {
        return $this->changed(new self($this->origin, $this->type, $this->language, $fromSql, $this->toSql, $this->orReplace));
    }

    /**
     * Replaces or removes the TO SQL function.
     */
    public function withToSql(Routine\RoutineByName|Routine\RoutineBySignature|null $toSql): self
    {
        return $this->changed(new self($this->origin, $this->type, $this->language, $this->fromSql, $toSql, $this->orReplace));
    }

    /**
     * Chooses whether an existing transform is replaced.
     */
    public function withOrReplace(bool $orReplace): self
    {
        return $this->changed(new self($this->origin, $this->type, $this->language, $this->fromSql, $this->toSql, $orReplace));
    }
}
