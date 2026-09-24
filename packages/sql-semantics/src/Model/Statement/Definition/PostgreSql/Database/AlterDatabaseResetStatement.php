<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Database;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\ResetSetting;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes one stored configuration default of a PostgreSQL database.
 * @visibility public
 * @example Reading the removed default
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app RESET TIME ZONE');
 *     $statement->setting->name // => ['timezone']
 * @example Rejecting an empty database name
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app RESET work_mem');
 *     $statement->withName(''); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AlterDatabaseResetStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly ResetSetting $setting)
    {
        DatabaseInvariant::target($origin, $name);
        if ($setting->scope !== SettingScope::Session || $setting->ifExists) {
            throw new InvalidStructure('A stored database default is removed by its session parameter name.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the removal while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->setting);
    }

    /**
     * Replaces the database whose default is removed.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->setting));
    }

    /**
     * Replaces the removed parameter.
     */
    public function withSetting(ResetSetting $setting): self
    {
        return $this->changed(new self($this->origin, $this->name, $setting));
    }
}
