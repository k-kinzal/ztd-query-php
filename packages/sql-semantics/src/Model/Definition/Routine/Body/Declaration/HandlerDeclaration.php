<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body\Declaration;

use SqlSemantics\Model\Configuration\Condition\SqlState;
use SqlSemantics\Model\Definition\Routine\Body\ProgramStatement;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * DECLARE action HANDLER FOR conditions statement: runs the statement when a listed condition is raised in the block.
 * @visibility public
 * @example Reading handled conditions
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION, 1205 BEGIN END; END');
 *     $handler = $statement->body->declarations[0];
 *     $handler->action->value // => 'EXIT'
 *     count($handler->conditions) // => 2
 */
final class HandlerDeclaration
{
    /**
     * @var non-empty-list<ErrorCode|SqlState|NamedCondition|ConditionClass>
     */
    public readonly array $conditions;

    /**
     * Requires at least one condition, each listed once.
     * @param list<ErrorCode|SqlState|NamedCondition|ConditionClass> $conditions
     * @throws InvalidStructure
     */
    public function __construct(public readonly HandlerAction $action, array $conditions, public readonly ProgramStatement $statement)
    {
        Collections::alternatives($conditions, [ErrorCode::class, SqlState::class, NamedCondition::class, ConditionClass::class]);
        $keys = array_map(self::key(...), $conditions);
        if (count(array_unique($keys)) !== count($keys)) {
            throw new InvalidStructure('A handler lists each condition once.');
        }
        $this->conditions = Collections::nonEmpty($conditions);
    }

    /**
     * Returns a comparison key identifying one handled condition.
     */
    public static function key(ErrorCode|SqlState|NamedCondition|ConditionClass $condition): string
    {
        return match (true) {
            $condition instanceof ErrorCode => 'error:' . $condition->number(),
            $condition instanceof SqlState => 'state:' . $condition->code,
            $condition instanceof NamedCondition => 'name:' . strtolower($condition->name),
            default => 'class:' . $condition->value,
        };
    }
}
