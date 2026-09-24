<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Configuration\System;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\ResetSetting;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes one server configuration parameter from the PostgreSQL automatic configuration file.
 * @visibility public
 * @example Reading the removed parameter
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER SYSTEM RESET shared_buffers');
 *     $statement->setting->name // => ['shared_buffers']
 * @example Rejecting a persisted MySQL reset
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER SYSTEM RESET shared_buffers');
 *     $statement->withSetting(new \SqlSemantics\Model\Configuration\ResetSetting(['x'], \SqlSemantics\Model\Configuration\SettingScope::Persist, $statement->source)); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AlterSystemResetStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly ResetSetting $setting)
    {
        if ($origin->dialect !== Dialect::PostgreSql || $setting->scope !== SettingScope::Session || $setting->ifExists) {
            throw new InvalidStructure('ALTER SYSTEM RESET requires PostgreSQL and an unscoped parameter name.');
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
        return new self($origin, $this->setting);
    }

    /**
     * Replaces the removed parameter.
     */
    public function withSetting(ResetSetting $setting): self
    {
        return $this->changed(new self($this->origin, $setting));
    }
}
