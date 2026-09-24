<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration;

use SqlParser\Parser\Node;

/**
 * A named setting effect with an operation-specific payload.
 * @visibility public
 * @example Reading a bound setting
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $statement = $binder->bind("SET SESSION work_mem = '4MB'");
 *     $setting = $statement->settings[0];
 *     $setting instanceof \SqlSemantics\Model\Configuration\Setting // => true
 *     $setting->name // => ['work_mem']
 *     $setting->action // => \SqlSemantics\Model\Configuration\SettingAction::Assign
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
        if ($name === [] || (in_array('', $name, true) && !($scope === SettingScope::User && $name === ['']))) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('An identifier requires nonempty name parts; only a user variable may be named by the empty string.');
        }
        $this->name = $name;
        $this->action = $this->operation();
    }

    abstract protected function operation(): SettingAction;
}
