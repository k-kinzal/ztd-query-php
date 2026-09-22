<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration;

use Override;

/**
 * Assigns a configuration parameter's default without carrying a scalar expression.
 * @visibility public
  * @example Inspecting DefaultSetting
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $statement = $binder->bind('SET search_path TO public, example');
 *     $binder->bind('SET work_mem TO DEFAULT')->settings[0] instanceof \SqlSemantics\Model\Configuration\DefaultSetting // => true
 */
final class DefaultSetting extends Setting
{
    #[Override]
    protected function operation(): SettingAction
    {
        return SettingAction::Default;
    }
}
