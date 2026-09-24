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
 * Records the current provider version of one collation.
 * @visibility public
 * @example Refreshing a collation version
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER COLLATION app.german REFRESH VERSION');
 *     $statement->collation->parts // => ['app', 'german']
 *     $statement->toString() // => 'ALTER COLLATION "app"."german" REFRESH VERSION'
 */
final class RefreshCollationVersionStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $collation)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($collation);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->collation);
    }

    /**
     * Replaces the refreshed collation.
     */
    public function withCollation(QualifiedName $collation): self
    {
        return $this->changed(new self($this->origin, $collation));
    }
}
