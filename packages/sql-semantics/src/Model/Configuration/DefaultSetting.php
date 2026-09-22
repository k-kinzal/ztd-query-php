<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration;

use Override;

/**
 * Assigns a configuration parameter's default without carrying a scalar expression.
 * @visibility public
 */
final class DefaultSetting extends Setting
{
    #[Override]
    protected function operation(): SettingAction
    {
        return SettingAction::Default;
    }
}
