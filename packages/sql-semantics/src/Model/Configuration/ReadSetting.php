<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration;

use Override;
use SqlParser\Parser\Node;

/**
 * ReadSetting exposes only the operands required by its operation.
 * @visibility public
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
