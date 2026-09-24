<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration;

use Override;
use SqlParser\Parser\Node;

/**
 * ReadSetting exposes only the operands required by its operation.
 * @visibility public
 * @example Describing a setting read
 *     $setting = new \SqlSemantics\Model\Configuration\ReadSetting(['main', 'cache_size'], \SqlSemantics\Model\Configuration\SettingScope::Database, new \SqlParser\Parser\Node('setting', 0, []));
 *     $setting->action // => \SqlSemantics\Model\Configuration\SettingAction::Read
 *     $setting->name // => ['main', 'cache_size']
 *     new \SqlSemantics\Model\Configuration\ReadSetting([], \SqlSemantics\Model\Configuration\SettingScope::Database, new \SqlParser\Parser\Node('setting', 0, [])) // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class ReadSetting extends Setting
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
        return SettingAction::Read;
    }
}
