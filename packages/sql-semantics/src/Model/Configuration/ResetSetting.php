<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration;

use Override;
use SqlParser\Parser\Node;

/**
 * ResetSetting exposes only the operands required by its operation.
 * @visibility public
  * @example Inspecting ResetSetting
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $set = $binder->bind('SET work_mem TO DEFAULT')->settings[0];
 *     $reset = $binder->bind('RESET work_mem')->setting;
 *     $current = $binder->bind('SET work_mem FROM CURRENT')->settings[0];
 *     $reset instanceof \SqlSemantics\Model\Configuration\ResetSetting // => true
 */
final class ResetSetting extends Setting
{
    /**
     * @param list<string> $name
     */
    public function __construct(array $name, SettingScope $scope, Node $source, public readonly bool $ifExists = false)
    {
        parent::__construct($name, $scope, $source);
    }

    #[Override]
    protected function operation(): SettingAction
    {
        return SettingAction::Reset;
    }
}
