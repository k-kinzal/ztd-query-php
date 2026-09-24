<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Type;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\Enumeration\EnumLabels;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates an enum type from its ordered, unique labels, which may be empty.
 * @visibility public
 * @example Creating an enum type
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE TYPE mood AS ENUM ('sad', 'ok', 'happy')");
 *     $statement->labels // => ['sad', 'ok', 'happy']
 * @example Rejecting a repeated label
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE TYPE mood AS ENUM ('sad')");
 *     $statement->withLabels(['sad', 'sad']); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateEnumTypeStatement extends BoundStatement
{
    /**
     * @param list<string> $labels
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly array $labels)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($name);
        Collections::strings($labels);
        EnumLabels::labels($labels);
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
        return new self($origin, $this->name, $this->labels);
    }

    /**
     * Replaces the type name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->labels));
    }

    /**
     * Replaces the labels.
     * @param list<string> $labels
     */
    public function withLabels(array $labels): self
    {
        return $this->changed(new self($this->origin, $this->name, $labels));
    }
}
