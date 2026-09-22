<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration;

use Override;
use SqlParser\Parser\Node;

/**
 * CurrentSetting exposes only the operands required by its operation.
 * @visibility public
  * @example Inspecting CurrentSetting
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $set = $binder->bind('SET work_mem TO DEFAULT')->settings[0];
 *     $reset = $binder->bind('RESET work_mem')->setting;
 *     $current = $binder->bind('SET work_mem FROM CURRENT')->settings[0];
 *     $current instanceof \SqlSemantics\Model\Configuration\CurrentSetting // => true
 */
final class CurrentSetting extends Setting
{
    /**
     * @param list<string> $name
     */
    public function __construct(array $name, SettingScope $scope, Node $source)
    {
        parent::__construct($name, $scope, $source);
    }

    #[Override]
    protected function operation(): SettingAction
    {
        return SettingAction::CopyCurrent;
    }
}
