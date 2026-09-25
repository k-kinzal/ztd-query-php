<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Replication;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Replication\Publication\PublicationInvariant;
use SqlSemantics\Model\Definition\Replication\Publication\PublicationMember;
use SqlSemantics\Model\Definition\Replication\Publication\PublicationObjectChange;
use SqlSemantics\Model\Definition\Replication\Publication\PublishedTable;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Adds objects to, replaces the objects of, or removes objects from a publication.
 * @visibility public
 * @example Reading an object change of a publication
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('ALTER PUBLICATION pub ADD TABLE t, TABLES IN SCHEMA CURRENT_SCHEMA');
 *     $statement->change // => \SqlSemantics\Model\Definition\Replication\Publication\PublicationObjectChange::Add
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'ALTER PUBLICATION "pub" ADD TABLE "public"."t", TABLES IN SCHEMA CURRENT_SCHEMA'
 */
final class AlterPublicationObjectsStatement extends BoundStatement
{
    /**
     * @var non-empty-list<PublicationMember> Validated objects in written order
     */
    public readonly array $objects;

    /**
     * @param list<PublicationMember> $objects
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly PublicationObjectChange $change,
        array $objects,
    ) {
        PublicationInvariant::identity($origin, $name);
        $this->objects = PublicationInvariant::objects($objects);
        foreach ($this->objects as $object) {
            if ($change === PublicationObjectChange::Drop && $object instanceof PublishedTable && ($object->columns !== [] || $object->filter !== null)) {
                throw new InvalidStructure('A table removed from a publication takes neither a column list nor a row filter.');
            }
        }
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
        return new self($origin, $this->name, $this->change, $this->objects);
    }

    /**
     * Replaces the publication name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->change, $this->objects));
    }

    /**
     * Replaces how the objects change the publication.
     */
    public function withChange(PublicationObjectChange $change): self
    {
        return $this->changed(new self($this->origin, $this->name, $change, $this->objects));
    }

    /**
     * Replaces the published objects.
     * @param list<PublicationMember> $objects
     */
    public function withObjects(array $objects): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->change, $objects));
    }
}
