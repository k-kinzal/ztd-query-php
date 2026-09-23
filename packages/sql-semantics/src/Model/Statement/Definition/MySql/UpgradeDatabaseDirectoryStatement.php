<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Database\DatabaseInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests conversion of a legacy database directory name to the newer identifier encoding.
 * @visibility public
 * @example Inspecting a legacy upgrade request
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER DATABASE old UPGRADE DATA DIRECTORY NAME');
 *     $statement->name // => 'old'
 */
final class UpgradeDatabaseDirectoryStatement extends BoundStatement
{
    /**
     * Requires a named database in a release that supplies this upgrade operation.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name)
    {
        DatabaseInvariant::upgrade($origin, $name);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the upgrade request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name);
    }

    /**
     * Replaces the legacy database identity.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name));
    }
}
