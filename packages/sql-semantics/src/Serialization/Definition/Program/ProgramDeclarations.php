<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Program;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration;
use SqlSemantics\Model\Configuration\Condition\SqlState;
use SqlSemantics\Model\Definition\Routine\Body\AssignmentStatement;
use SqlSemantics\Model\Definition\Routine\Body\Declaration;
use SqlSemantics\Model\Definition\Routine\Stored\DeclaredDomain;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Query\Queries;
use SqlSemantics\Serialization\Settings;
use SqlSemantics\Serialization\TypeDeclaration;

/**
 * Writes the declarations and assignments of a stored program body.
 * @visibility SqlSemantics
 */
final class ProgramDeclarations
{
    /**
     * Writes one DECLARE without its terminating semicolon.
     */
    public static function write(Declaration\VariableDeclaration|Declaration\ConditionDeclaration|Declaration\CursorDeclaration|Declaration\HandlerDeclaration $declaration): Tree
    {
        return match (true) {
            $declaration instanceof Declaration\VariableDeclaration => new Tree('declare-variable', [
                Build::keyword('DECLARE'), Build::separated(array_map(static fn (string $name): Tree => Build::identifier([$name], Dialect::MySql), $declaration->names)), self::domain($declaration->domain),
                ...($declaration->default === null ? [] : [Build::keyword('DEFAULT'), Expressions::write($declaration->default)]),
            ]),
            $declaration instanceof Declaration\ConditionDeclaration => new Tree('declare-condition', [Build::keyword('DECLARE'), Build::identifier([$declaration->name], Dialect::MySql), Build::keyword('CONDITION FOR'), self::condition($declaration->value)]),
            $declaration instanceof Declaration\CursorDeclaration => new Tree('declare-cursor', [Build::keyword('DECLARE'), Build::identifier([$declaration->name], Dialect::MySql), Build::keyword('CURSOR FOR'), Queries::write($declaration->query)]),
            default => new Tree('declare-handler', [
                Build::keyword('DECLARE ' . $declaration->action->value . ' HANDLER FOR'),
                Build::separated(array_map(static fn (Declaration\ErrorCode|SqlState|Declaration\NamedCondition|Declaration\ConditionClass $condition): Tree => match (true) {
                    $condition instanceof Declaration\NamedCondition => Build::identifier([$condition->name], Dialect::MySql),
                    $condition instanceof Declaration\ConditionClass => Build::keyword($condition->value),
                    default => self::condition($condition),
                }, $declaration->conditions)),
                ProgramBodies::write($declaration->statement),
            ]),
        };
    }

    /**
     * Writes an error number as written or an SQLSTATE string.
     */
    public static function condition(Declaration\ErrorCode|SqlState $condition): Tree
    {
        return $condition instanceof SqlState ? new Tree('sqlstate', [Build::keyword('SQLSTATE'), new Tree('literal', [new Atom('literal', "'" . $condition->code . "'")])]) : new Tree('error-code', [new Atom('number', $condition->spelling)]);
    }

    /**
     * Writes a declared type, its ZEROFILL display and its collation.
     */
    public static function domain(DeclaredDomain $domain): Tree
    {
        return new Tree('domain', [TypeDeclaration::write($domain->type), ...($domain->zeroFill ? [Build::keyword('ZEROFILL')] : []), ...($domain->collation === null ? [] : [Build::keyword('COLLATE'), Build::identifier([$domain->collation], Dialect::MySql)])]);
    }

    /**
     * Writes SET items in order; a session setting after a scoped one spells SESSION, as for an ordinary SET.
     */
    public static function assignments(AssignmentStatement $statement): Tree
    {
        $items = [];
        $carried = Configuration\SettingScope::Session;
        foreach ($statement->assignments as $assignment) {
            if ($assignment instanceof Declaration\LocalAssignment || $assignment instanceof Declaration\TriggerRowAssignment) {
                $items[] = new Tree('assignment', [Expressions::write($assignment->target), Build::keyword('='), Expressions::write($assignment->value)]);
                continue;
            }
            $items[] = Settings::assignment($assignment, Dialect::MySql, $carried);
            if ($assignment instanceof Configuration\AssignedSetting || $assignment instanceof Configuration\DefaultSetting) {
                $carried = $assignment->scope;
            }
        }
        return new Tree('set', [Build::keyword('SET'), Build::separated($items)]);
    }
}
