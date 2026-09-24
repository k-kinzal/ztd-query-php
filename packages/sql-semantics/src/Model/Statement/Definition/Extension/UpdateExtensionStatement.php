<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\Extension;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Updates an installed extension to a named version, or to its default version when none is named.
 * @visibility public
 * @example Reading the target version
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("ALTER EXTENSION hstore UPDATE TO '1.8'");
 *     $statement->name // => 'hstore'
 *     $statement->version // => '1.8'
 * @example Updating to the default version
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER EXTENSION hstore UPDATE');
 *     $statement->version // => null
 */
final class UpdateExtensionStatement extends BoundStatement
{
    /**
     * @param string|null $version Target version (TO); null selects the default version
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly ?string $version)
    {
        ExtensionInvariant::dialect($origin);
        ExtensionInvariant::names($name, $version);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the update request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->version);
    }

    /**
     * Replaces the extension to update.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->version));
    }

    /**
     * Replaces the target version; null selects the default version.
     */
    public function withVersion(?string $version): self
    {
        return $this->changed(new self($this->origin, $this->name, $version));
    }
}
