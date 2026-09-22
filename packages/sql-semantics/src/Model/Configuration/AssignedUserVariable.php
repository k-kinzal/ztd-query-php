<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Reference;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\VariableScope;

/**
 * Assigns one expression to one MySQL user-variable storage location.
 * @visibility public
  * @example Inspecting AssignedUserVariable
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SET @a=123');
 *     $assignment = $statement->settings[0];
 *     $assignment instanceof \SqlSemantics\Model\Configuration\AssignedUserVariable // => true
 */
final class AssignedUserVariable extends Setting
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Reference\VariableReference|Reference\UnresolvedVariableReference $target, public readonly Expression $value, Node $source)
    {
        $scope = $target instanceof Reference\VariableReference ? $target->definition->scope : $target->scope;
        $name = $target instanceof Reference\VariableReference ? $target->definition->name : $target->name;
        if ($scope !== VariableScope::User || $target->type->dialect !== Dialect::MySql || $value->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('A user-variable assignment requires a MySQL user-variable target and value expression.');
        }
        parent::__construct([$name], SettingScope::User, $source);
    }

    #[Override]
    protected function operation(): SettingAction
    {
        return SettingAction::Assign;
    }
}
