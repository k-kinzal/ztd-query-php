<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Program;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Definition\Routine\Characteristics\SqlDataAccess;
use SqlSemantics\Model\Definition\Routine\Stored\EventCompletion;
use SqlSemantics\Model\Definition\Routine\Stored\EventStatus;
use SqlSemantics\Model\Definition\Routine\Stored\ExternalRoutineCode;
use SqlSemantics\Model\Definition\Routine\Stored\FunctionParameter;
use SqlSemantics\Model\Definition\Routine\Stored\OneTimeSchedule;
use SqlSemantics\Model\Definition\Routine\Stored\ProcedureParameter;
use SqlSemantics\Model\Definition\Routine\Stored\RecurringSchedule;
use SqlSemantics\Model\Definition\Routine\Stored\RoutineCharacteristics;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\MySql\Program as Statement;
use SqlSemantics\Serialization\Definition\MySqlRemovals;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Query\Relations;

/**
 * Writes stored procedure, function, trigger and event definitions from their operands; defaults are omitted.
 * @visibility SqlSemantics
 */
final class StoredPrograms
{
    /**
     * Returns null for statements outside the stored program family.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Statement\CreateProcedureStatement, $statement instanceof Statement\CreateFunctionStatement => self::routine($statement),
            $statement instanceof Statement\CreateTriggerStatement => new Tree('create-trigger', [
                ...self::head($statement->definer, 'TRIGGER', $statement->ifNotExists), Build::identifier($statement->name->parts, Dialect::MySql),
                Build::keyword($statement->timing->value . ' ' . $statement->event->value . ' ON'), Relations::target($statement->table, Dialect::MySql), Build::keyword('FOR EACH ROW'),
                ...($statement->order === null ? [] : [Build::keyword($statement->order->position->value), Build::identifier([$statement->order->trigger], Dialect::MySql)]),
                ProgramBodies::write($statement->body),
            ]),
            $statement instanceof Statement\CreateEventStatement => new Tree('create-event', [
                ...self::head($statement->definer, 'EVENT', $statement->ifNotExists), Build::identifier($statement->name->parts, Dialect::MySql), self::schedule($statement->schedule),
                ...($statement->completion === EventCompletion::Drop ? [] : [Build::keyword($statement->completion->value)]),
                ...($statement->status === EventStatus::Enabled ? [] : [Build::keyword($statement->status->value)]),
                ...self::comment($statement->comment), Build::keyword('DO'), ProgramBodies::write($statement->body),
            ]),
            $statement instanceof Statement\AlterEventStatement => self::alterEvent($statement),
            default => null,
        };
    }

    /**
     * Writes CREATE PROCEDURE or CREATE FUNCTION with parameters, return domain, characteristics and body.
     */
    public static function routine(Statement\CreateProcedureStatement|Statement\CreateFunctionStatement $statement): Tree
    {
        $function = $statement instanceof Statement\CreateFunctionStatement;
        $parameters = array_map(static fn (ProcedureParameter|FunctionParameter $parameter): Tree => new Tree('parameter', [
            ...($parameter instanceof ProcedureParameter ? [Build::keyword($parameter->mode->value)] : []), Build::identifier([$parameter->name], Dialect::MySql), ProgramDeclarations::domain($parameter->domain),
        ]), $statement->parameters);
        $body = $statement->body instanceof ExternalRoutineCode
            ? [Build::keyword('LANGUAGE'), Build::identifier([$statement->body->language], Dialect::MySql), Build::keyword('AS'), new Tree('literal', [new Atom('literal', "'" . str_replace(['\\', "'"], ['\\\\', "''"], $statement->body->code) . "'")])]
            : [ProgramBodies::write($statement->body)];
        return new Tree('create-routine', [
            ...self::head($statement->definer, $function ? 'FUNCTION' : 'PROCEDURE', $statement->ifNotExists), Build::identifier($statement->name->parts, Dialect::MySql),
            new Tree('parameters', [new Atom('punctuation', '('), Build::separated($parameters), new Atom('punctuation', ')')]),
            ...($statement instanceof Statement\CreateFunctionStatement ? [Build::keyword('RETURNS'), ProgramDeclarations::domain($statement->returns)] : []),
            ...self::characteristics($statement->characteristics), ...$body,
        ]);
    }

    /**
     * Writes CREATE [DEFINER = account] kind [IF NOT EXISTS].
     * @return list<Tree>
     */
    public static function head(AccountName|CurrentAccount|null $definer, string $kind, bool $ifNotExists): array
    {
        return [Build::keyword('CREATE'), ...self::definer($definer), Build::keyword($kind . ($ifNotExists ? ' IF NOT EXISTS' : ''))];
    }

    /**
     * Writes DEFINER = account when a definer is given.
     * @return list<Tree>
     */
    public static function definer(AccountName|CurrentAccount|null $definer): array
    {
        return $definer === null ? [] : [Build::keyword('DEFINER ='), MySqlRemovals::accounts([$definer])];
    }

    /**
     * Writes the characteristics that differ from the server defaults.
     * @return list<Tree>
     */
    public static function characteristics(RoutineCharacteristics $characteristics): array
    {
        return [
            ...self::comment($characteristics->comment),
            ...($characteristics->deterministic ? [Build::keyword('DETERMINISTIC')] : []),
            ...($characteristics->dataAccess === SqlDataAccess::Contains ? [] : [Build::keyword($characteristics->dataAccess->value)]),
            ...($characteristics->security === RoutineSecurity::Definer ? [] : [Build::keyword('SQL SECURITY ' . $characteristics->security->value)]),
        ];
    }

    /**
     * Writes COMMENT with the literal's original spelling.
     * @return list<Tree>
     */
    public static function comment(?Literal $comment): array
    {
        return $comment === null ? [] : [Build::keyword('COMMENT'), Expressions::write($comment)];
    }

    /**
     * Writes ON SCHEDULE AT time or EVERY interval unit with its window.
     */
    public static function schedule(OneTimeSchedule|RecurringSchedule $schedule): Tree
    {
        if ($schedule instanceof OneTimeSchedule) {
            return new Tree('schedule', [Build::keyword('ON SCHEDULE AT'), self::operand($schedule->at)]);
        }
        return new Tree('schedule', [
            Build::keyword('ON SCHEDULE EVERY'), self::operand($schedule->every), Build::keyword($schedule->unit->value),
            ...($schedule->starts === null ? [] : [Build::keyword('STARTS'), self::operand($schedule->starts)]),
            ...($schedule->ends === null ? [] : [Build::keyword('ENDS'), self::operand($schedule->ends)]),
        ]);
    }

    /**
     * Parenthesizes a schedule expression other than a literal, so the following clause cannot extend it.
     */
    public static function operand(\SqlSemantics\Model\Expression $expression): Tree
    {
        $written = Expressions::write($expression);
        return $expression instanceof Literal ? $written : Build::parentheses($written);
    }

    /**
     * Writes ALTER EVENT with exactly the requested changes.
     */
    public static function alterEvent(Statement\AlterEventStatement $statement): Tree
    {
        $changes = $statement->changes;
        return new Tree('alter-event', [
            Build::keyword('ALTER'), ...self::definer($statement->definer), Build::keyword('EVENT'), Build::identifier($statement->name->parts, Dialect::MySql),
            ...($changes->schedule === null ? [] : [self::schedule($changes->schedule)]),
            ...($changes->completion === null ? [] : [Build::keyword($changes->completion->value)]),
            ...($changes->newName === null ? [] : [Build::keyword('RENAME TO'), Build::identifier($changes->newName->parts, Dialect::MySql)]),
            ...($changes->status === null ? [] : [Build::keyword($changes->status->value)]),
            ...self::comment($changes->comment),
            ...($changes->body === null ? [] : [Build::keyword('DO'), ProgramBodies::write($changes->body)]),
        ]);
    }
}
