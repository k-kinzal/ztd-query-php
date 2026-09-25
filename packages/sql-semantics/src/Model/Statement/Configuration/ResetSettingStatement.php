<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Configuration;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\ResetSetting;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Statement\ConfigurationStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Resets one named parameter, with the database's required reset scope.
 * @example Reading the operation's structure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('RESET PERSIST IF EXISTS max_connections', strict: false);
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'RESET PERSIST IF EXISTS `max_connections`'
 *
 * @visibility public
 */
final class ResetSettingStatement extends ConfigurationStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly ResetSetting $setting)
    {
        $valid = match ($origin->dialect) {
            Dialect::MySql => $setting->scope === SettingScope::Persist,
            Dialect::PostgreSql => $setting->scope === SettingScope::Session && !$setting->ifExists,
            Dialect::Sqlite => false,
        };
        if (!$valid) {
            throw new InvalidStructure('The reset target must use the database reset scope.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Reset;
    }

    /**
     * Retains the named reset while replacing its diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->setting);
    }
}
