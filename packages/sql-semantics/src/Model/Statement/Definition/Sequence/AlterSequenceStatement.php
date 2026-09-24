<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\Sequence;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\Relation\Identity;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Changes the options of an existing sequence; RESTART sets the next value and omitted options keep their current settings.
 * @visibility public
 * @example Reading the changed options
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE IF EXISTS s NO MAXVALUE RESTART WITH 7 OWNED BY NONE');
 *     $statement->options[0] // => \SqlSemantics\Model\Definition\Relation\Identity\SequenceFlag::NoMaxValue
 *     $statement->options[1]->value->text // => '7'
 *     $statement->options[2]->column // => null
 * @example Rejecting an empty change list
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE s CYCLE');
 *     $statement->withOptions([]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AlterSequenceStatement extends BoundStatement
{
    /**
     * @param non-empty-list<Identity\SequenceValueChange|Identity\SequenceFlag|Identity\SequenceStorage|Identity\SetSequenceOwner|Identity\RestartIdentity> $options Changes in request order
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly bool $ifExists, public readonly array $options)
    {
        SequenceInvariant::dialect($origin);
        CatalogInvariant::name($name, 3);
        $keyed = SequenceInvariant::options(Collections::nonEmpty($options));
        SequenceInvariant::bounds($keyed, SequenceInvariant::value($keyed, Identity\SequenceAttribute::MinValue), SequenceInvariant::value($keyed, Identity\SequenceAttribute::MaxValue));
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the option changes while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->ifExists, $this->options);
    }

    /**
     * Replaces the altered sequence.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->ifExists, $this->options));
    }

    /**
     * Replaces whether a missing sequence is accepted.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->name, $ifExists, $this->options));
    }

    /**
     * Replaces the option changes.
     * @param non-empty-list<Identity\SequenceValueChange|Identity\SequenceFlag|Identity\SequenceStorage|Identity\SetSequenceOwner|Identity\RestartIdentity> $options
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->ifExists, $options));
    }
}
