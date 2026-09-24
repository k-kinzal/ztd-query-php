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
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates a sequence generator; omitted options take the server defaults for the type and direction.
 * @visibility public
 * @example Reading a descending sequence
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE TEMP SEQUENCE IF NOT EXISTS s AS integer INCREMENT BY -2 MINVALUE -100 START WITH -5');
 *     $statement->persistence // => \SqlSemantics\Model\Statement\Definition\Sequence\SequencePersistence::Temporary
 *     $statement->options[0]->type->name // => 'integer'
 *     $statement->options[1]->value->text // => '-2'
 *     $statement->options[3]->value->text // => '-5'
 * @example Rejecting a start below the minimum
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE SEQUENCE s MINVALUE 10');
 *     $statement->withOptions([$statement->options[0], new \SqlSemantics\Model\Definition\Relation\Identity\SequenceValueChange(\SqlSemantics\Model\Definition\Relation\Identity\SequenceAttribute::Start, \SqlSemantics\Model\Expression::literal(5, \SqlSemantics\Dialect::PostgreSql))]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateSequenceStatement extends BoundStatement
{
    /**
     * @param SequencePersistence $persistence Lifetime and logging of the sequence (TEMPORARY, UNLOGGED)
     * @param list<Identity\SequenceValueChange|Identity\SequenceFlag|Identity\SequenceStorage|Identity\SetSequenceOwner|Identity\RestartIdentity> $options Options in request order
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly SequencePersistence $persistence, public readonly bool $ifNotExists, public readonly array $options)
    {
        SequenceInvariant::dialect($origin);
        CatalogInvariant::name($name, 3);
        $schema = $name->parts[count($name->parts) - 2] ?? null;
        if ($persistence === SequencePersistence::Temporary && $schema !== null && !str_starts_with($schema, 'pg_temp')) {
            throw new InvalidStructure('A temporary sequence belongs to the temporary schema.');
        }
        SequenceInvariant::creation(SequenceInvariant::options($options));
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the sequence definition while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->persistence, $this->ifNotExists, $this->options);
    }

    /**
     * Replaces the sequence name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->persistence, $this->ifNotExists, $this->options));
    }

    /**
     * Replaces the lifetime and logging of the sequence.
     */
    public function withPersistence(SequencePersistence $persistence): self
    {
        return $this->changed(new self($this->origin, $this->name, $persistence, $this->ifNotExists, $this->options));
    }

    /**
     * Replaces whether an existing relation of this name is accepted.
     */
    public function withIfNotExists(bool $ifNotExists): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->persistence, $ifNotExists, $this->options));
    }

    /**
     * Replaces the options.
     * @param list<Identity\SequenceValueChange|Identity\SequenceFlag|Identity\SequenceStorage|Identity\SetSequenceOwner|Identity\RestartIdentity> $options
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->persistence, $this->ifNotExists, $options));
    }
}
