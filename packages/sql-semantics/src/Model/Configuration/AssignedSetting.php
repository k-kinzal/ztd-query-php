<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration;

use Override;
use SqlParser\Parser\Node;

/**
 * AssignedSetting exposes only the operands required by its operation.
 * @visibility public
 * @example Reading the assigned expressions
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SET LOCAL search_path TO public, other');
 *     $setting = $statement->settings[0];
 *     count($setting->values) // => 2
 */
final class AssignedSetting extends Setting
{
    /**
     * @var non-empty-list<\SqlSemantics\Model\Expression> Validated ordered operands
     */
    public readonly array $values;

    /**
     * @param list<string> $name
     * @param list<\SqlSemantics\Model\Expression> $values
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(array $name, SettingScope $scope, Node $source, array $values)
    {
        \SqlSemantics\Model\Validation\Collections::objects($values, \SqlSemantics\Model\Expression::class);
        if ($values === []) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A setting assignment requires its expressions.');
        }
        parent::__construct($name, $scope, $source);
        $this->values = \SqlSemantics\Model\Validation\Collections::nonEmpty($values);
    }

    #[Override]
    protected function operation(): SettingAction
    {
        return SettingAction::Assign;
    }
}
