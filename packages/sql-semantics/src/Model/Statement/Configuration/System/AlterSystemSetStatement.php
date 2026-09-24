<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Configuration\System;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\AssignedSetting;
use SqlSemantics\Model\Configuration\DefaultSetting;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Writes one server configuration parameter to the PostgreSQL automatic configuration file.
 * @visibility public
 * @example Reading the persisted assignment
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("ALTER SYSTEM SET work_mem = '64MB'");
 *     $statement->setting->name // => ['work_mem']
 * @example Rejecting a scoped assignment
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER SYSTEM SET work_mem TO DEFAULT');
 *     $statement->withSetting(new \SqlSemantics\Model\Configuration\DefaultSetting(['work_mem'], \SqlSemantics\Model\Configuration\SettingScope::Local, $statement->source)); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AlterSystemSetStatement extends BoundStatement
{
    /**
     * DEFAULT removes the entry from the automatic configuration file.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly AssignedSetting|DefaultSetting $setting)
    {
        if ($origin->dialect !== Dialect::PostgreSql || $setting->scope !== SettingScope::Session) {
            throw new InvalidStructure('ALTER SYSTEM requires PostgreSQL and an unscoped parameter.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the assignment while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->setting);
    }

    /**
     * Replaces the persisted assignment.
     */
    public function withSetting(AssignedSetting|DefaultSetting $setting): self
    {
        return $this->changed(new self($this->origin, $setting));
    }
}
