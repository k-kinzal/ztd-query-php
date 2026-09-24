<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\Extension;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Loads an extension into the current database, optionally into a schema, at a version, and with its prerequisites.
 * A parameterless CREATE LANGUAGE binds to this form because the server executes it as CREATE EXTENSION.
 * @visibility public
 * @example Reading the requested extension
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE EXTENSION IF NOT EXISTS hstore WITH SCHEMA app VERSION '1.8' CASCADE");
 *     $statement->name // => 'hstore'
 *     $statement->schema // => 'app'
 *     $statement->version // => '1.8'
 *     $statement->cascade // => true
 * @example Rejecting an empty version
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE EXTENSION hstore');
 *     $statement->withVersion(''); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateExtensionStatement extends BoundStatement
{
    /**
     * @param string|null $schema Schema receiving the extension objects (SCHEMA)
     * @param string|null $version Installed version (VERSION)
     * @param bool $cascade Whether missing prerequisite extensions are installed too (CASCADE)
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly bool $ifNotExists, public readonly ?string $schema, public readonly ?string $version, public readonly bool $cascade)
    {
        ExtensionInvariant::dialect($origin);
        ExtensionInvariant::names($name, $schema, $version);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the extension request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->ifNotExists, $this->schema, $this->version, $this->cascade);
    }

    /**
     * Replaces the extension to load.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->ifNotExists, $this->schema, $this->version, $this->cascade));
    }

    /**
     * Replaces whether an installed extension of this name is accepted without change.
     */
    public function withIfNotExists(bool $ifNotExists): self
    {
        return $this->changed(new self($this->origin, $this->name, $ifNotExists, $this->schema, $this->version, $this->cascade));
    }

    /**
     * Replaces the schema that receives the extension objects; null lets the extension choose.
     */
    public function withSchema(?string $schema): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->ifNotExists, $schema, $this->version, $this->cascade));
    }

    /**
     * Replaces the installed version; null installs the default version.
     */
    public function withVersion(?string $version): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->ifNotExists, $this->schema, $version, $this->cascade));
    }

    /**
     * Replaces whether missing prerequisites are installed too.
     */
    public function withCascade(bool $cascade): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->ifNotExists, $this->schema, $this->version, $cascade));
    }
}
