<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\RelationKinds;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Schedule;
use SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTable;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTime;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Statement;

/**
 * Derives the statements that define stored programs: routines, triggers and events.
 *
 * Rule: MYSQL-ROUTINE-001, MYSQL-TRIGGER-001, MYSQL-EVENT-001. A routine
 * body is derived in a scope whose outermost level holds the parameters as
 * one row (MYSQL-ROUTINE-PARAMETERS-001); a parameter name written twice is
 * reported (ER_SP_DUP_PARAM) and keeps its first meaning. A stored function
 * whose SQL body holds no RETURN is reported (ER_SP_NORETURN). A trigger
 * body is derived in a scope where NEW and OLD denote the row of the
 * trigger table (MYSQL-TRIGGER-ROWS-001) and SET assigns to the NEW row
 * by MYSQL-TRIGGER-ASSIGNMENT-001; a trigger on a declared view is refused
 * (MYSQL-RELATION-KIND-001). The expressions of an event schedule see
 * no table and no variable; the event body is derived in an empty scope.
 * None of the statements declares anything or returns rows. The statements
 * a stored program may not contain are reported by
 * MYSQL-PROGRAM-RESTRICTIONS-001. Terminates: bodies are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-trigger.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-event.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ProgramFacts
{
    /**
     * Narrows a routine body to a program statement, an SQL statement or external code.
     *
     * @throws InvalidConstruction When the node is none of them
     */
    public function body(Node $body): ExternalBody|ProgramStatement|Statement
    {
        return $body instanceof ExternalBody ? $body : (new StatementSequence())->member($body);
    }

    /**
     * Derives the parameters and the body of a stored procedure or function.
     */
    public function routine(ParameterList $parameters, ExternalBody|ProgramStatement|Statement $body, ProgramKind $kind, QualifiedName $name, Derivation $derivation): void
    {
        $fact = $derivation->relation($parameters, $derivation->environment());
        $scope = new ProgramScope($derivation->environment(), $kind);
        $hidden = [];
        $seen = [];
        foreach ($parameters->parameters as $position => $parameter) {
            if ($scope->holds($seen, $parameter->name)) {
                $derivation->report(new ProgramProblem(ProgramRule::DuplicateParameter, $parameter->name->value));
                $hidden[] = $position;
            }
            $seen[] = $parameter->name;
        }
        if ($body instanceof ExternalBody) {
            return;
        }
        $environment = new Environment($derivation->context, null, [new VisibleRelation($parameters, $fact->shape, null, null, $hidden)]);
        (new BodyFacts())->statement($body, $derivation, new ProgramScope($environment, $kind));
        if ($kind === ProgramKind::Function && !(new ReturnSearch())->found($body)) {
            $derivation->report(new ProgramProblem(ProgramRule::MissingReturn, $name->name->value));
        }
    }

    /**
     * Derives the table and the body of a trigger.
     */
    public function trigger(TriggerTable $table, TriggerTime $time, TriggerEvent $event, ProgramStatement|Statement $body, Derivation $derivation): void
    {
        $fact = $derivation->relation($table, $derivation->environment());
        (new RelationKinds())->require($derivation, $table->name, $fact->table, RelationKind::BaseTable);
        $environment = new Environment($derivation->context, null, (new RowAliases())->visible($table, $fact, $event, $derivation->context));
        (new BodyFacts())->statement($body, $derivation, new ProgramScope($environment, ProgramKind::Trigger, time: $time, event: $event));
    }

    /**
     * Derives the schedule and the body of an event, each when present.
     */
    public function event(?Schedule $schedule, ProgramStatement|Statement|null $body, Derivation $derivation): void
    {
        $schedule?->deriveSchedule($derivation, $derivation->environment());
        if ($body !== null) {
            (new BodyFacts())->statement($body, $derivation, new ProgramScope($derivation->environment(), ProgramKind::Event));
        }
    }
}
