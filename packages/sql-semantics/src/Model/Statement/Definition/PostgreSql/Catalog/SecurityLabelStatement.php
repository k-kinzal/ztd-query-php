<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Applies or removes a security label on one catalog object, optionally for a named label provider.
 * @visibility public
 * @example Reading a provider-specific label
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("SECURITY LABEL FOR selinux ON TABLE app.users IS 'system_u:object_r:sepgsql_table_t:s0'");
 *     $statement->provider // => 'selinux'
 *     $statement->label->literalKind // => \SqlSemantics\Model\Scalar\Value\LiteralKind::Text
 * @example Removing a label with the default provider
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SECURITY LABEL ON ROLE alice IS NULL');
 *     [$statement->provider, $statement->label] // => [null, null]
 */
final class SecurityLabelStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly ObjectAddress $object, public readonly ?string $provider, public readonly ?Literal $label)
    {
        CatalogInvariant::dialect($origin);
        CatalogInvariant::label($object);
        CatalogInvariant::text($label);
        if ($provider === '') {
            throw new InvalidStructure('A label provider requires a nonempty name.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::SecurityLabel;
    }

    /**
     * Retains the object, provider, and label while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->object, $this->provider, $this->label);
    }

    /**
     * Replaces the labelled object in a separately validated statement.
     */
    public function withObject(ObjectAddress $object): self
    {
        return $this->changed(new self($this->origin, $object, $this->provider, $this->label));
    }

    /**
     * Replaces the label provider; null selects the only loaded provider.
     */
    public function withProvider(?string $provider): self
    {
        return $this->changed(new self($this->origin, $this->object, $provider, $this->label));
    }

    /**
     * Replaces the label text; null removes the label.
     */
    public function withLabel(?Literal $label): self
    {
        return $this->changed(new self($this->origin, $this->object, $this->provider, $label));
    }
}
