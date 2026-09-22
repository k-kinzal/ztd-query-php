<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration;

use SqlParser\Parser\Node;

/**
 * A named setting effect with an operation-specific payload.
 * @visibility public
 */
abstract class Setting
{
    /**
     * @var non-empty-list<string>
     */
    public readonly array $name;

    /**
     * Configuration effect derived from the concrete setting type.
     */
    public readonly SettingAction $action;

    /**

     * @param list<string> $name

     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(array $name, public readonly SettingScope $scope, public readonly Node $source)
    {
        \SqlSemantics\Model\Validation\Collections::strings($name);
        if ($name === [] || in_array('', $name, true)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('An identifier requires nonempty name parts.');
        }
        $this->name = $name;
        $this->action = $this->operation();
    }

    abstract protected function operation(): SettingAction;
}
