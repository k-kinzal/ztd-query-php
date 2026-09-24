<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Database;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\AssignedSetting;
use SqlSemantics\Model\Configuration\CurrentSetting;
use SqlSemantics\Model\Configuration\DefaultSetting;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Stores a configuration default that sessions connecting to one PostgreSQL database start with.
 * @visibility public
 * @example Reading the stored default
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app SET search_path TO app, public');
 *     $statement->name // => 'app'
 *     $statement->setting->name // => ['search_path']
 * @example Rejecting a non-session setting scope
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app SET work_mem = 1');
 *     $statement->withSetting(new \SqlSemantics\Model\Configuration\DefaultSetting(['work_mem'], \SqlSemantics\Model\Configuration\SettingScope::Local, $statement->source)); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AlterDatabaseSetStatement extends BoundStatement
{
    /**
     * The setting uses the session scope because the stored value applies when sessions start.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly AssignedSetting|DefaultSetting|CurrentSetting $setting)
    {
        DatabaseInvariant::target($origin, $name);
        if ($setting->scope !== SettingScope::Session) {
            throw new InvalidStructure('A stored database setting uses the session scope.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the stored default while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->setting);
    }

    /**
     * Replaces the database whose default changes.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->setting));
    }

    /**
     * Replaces the stored assignment.
     */
    public function withSetting(AssignedSetting|DefaultSetting|CurrentSetting $setting): self
    {
        return $this->changed(new self($this->origin, $this->name, $setting));
    }
}
