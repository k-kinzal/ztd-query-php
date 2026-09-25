<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Locale;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates a collation with the settings of an existing collation.
 * @visibility public
 * @example Copying a collation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE COLLATION IF NOT EXISTS app.german FROM "de_DE"');
 *     $statement->copied->parts // => ['de_DE']
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'CREATE COLLATION IF NOT EXISTS "app"."german" FROM "de_DE"'
 */
final class CopyCollationStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly QualifiedName $copied, public readonly bool $ifNotExists = false)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($name);
        TypeSystemInvariant::name($copied);
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
        return new self($origin, $this->name, $this->copied, $this->ifNotExists);
    }

    /**
     * Replaces the new collation name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->copied, $this->ifNotExists));
    }

    /**
     * Replaces the copied collation.
     */
    public function withCopied(QualifiedName $copied): self
    {
        return $this->changed(new self($this->origin, $this->name, $copied, $this->ifNotExists));
    }

    /**
     * Replaces the tolerance for an existing collation.
     */
    public function withIfNotExists(bool $ifNotExists): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->copied, $ifNotExists));
    }
}
