<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Replication;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Replication\Publication\PublicationInvariant;
use SqlSemantics\Model\Definition\Replication\Publication\PublicationMember;
use SqlSemantics\Model\Definition\Replication\Publication\PublicationOptions;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates a publication of listed tables and schemas.
 * @visibility public
 * @example Reading the listed objects of a publication
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT); CREATE TABLE t2(b INT)')))->bind('CREATE PUBLICATION pub FOR TABLE t (a), TABLE t2 WHERE (b > 0)');
 *     count($statement->objects) // => 2
 *     $statement->toString() // => 'CREATE PUBLICATION "pub" FOR TABLE "public"."t"("a"), TABLE "public"."t2" WHERE (("b" > 0))'
 */
final class CreateObjectsPublicationStatement extends BoundStatement
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
        array $objects,
        public readonly PublicationOptions $options = new PublicationOptions(),
    ) {
        PublicationInvariant::identity($origin, $name);
        $this->objects = PublicationInvariant::objects($objects);
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
        return new self($origin, $this->name, $this->objects, $this->options);
    }

    /**
     * Replaces the publication name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->objects, $this->options));
    }

    /**
     * Replaces the published objects.
     * @param list<PublicationMember> $objects
     */
    public function withObjects(array $objects): self
    {
        return $this->changed(new self($this->origin, $this->name, $objects, $this->options));
    }

    /**
     * Replaces the WITH options.
     */
    public function withOptions(PublicationOptions $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->objects, $options));
    }
}
