<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Command;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Attributes;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Conditions;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Indexes;
use SqlSemantics\Platform\PostgreSql\Rules\Table\PseudoRelations;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\CreateConstraintTrigger;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\CreateEventTrigger;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\CreateTrigger;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\TriggerArgument;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Derives and writes triggers and event triggers.
 *
 * Rule: PG-TRIGGER-001. The table of a trigger is resolved
 * (PG-TABLE-TARGET-001) and is the relation fact of the statement; inside
 * CREATE SCHEMA an unqualified table is in the schema being created. The WHEN
 * condition of a row trigger sees the columns of the table as OLD and NEW
 * ("the WHEN condition can refer to columns of the old and/or new row values
 * by writing OLD.column_name or NEW.column_name"); a statement trigger's
 * condition sees no column. ROW transition variables are reported as not
 * supported. An event trigger's event must be ddl_command_start,
 * ddl_command_end, sql_drop, table_rewrite or (PostgreSQL 17) login, and
 * its filter variable `tag`; others are reported.
 * Source: https://www.postgresql.org/docs/17/sql-createtrigger.html,
 * https://www.postgresql.org/docs/17/event-trigger-definition.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Triggers
{
    /**
     * The events an event trigger can fire on.
     */
    private const EVENTS = ['ddl_command_start', 'ddl_command_end', 'sql_drop', 'table_rewrite', 'login'];

    /**
     * Derives a trigger; `$schema` is the schema of an enclosing CREATE SCHEMA.
     */
    public function derive(CreateTrigger $trigger, Derivation $derivation, ?Name $schema): void
    {
        $name = (new Indexes())->located($trigger->table, $schema);
        $fact = $derivation->target($trigger, (new Targets())->resolve($derivation, $name));
        foreach ($trigger->transitions as $transition) {
            if (!$transition->table) {
                $derivation->report(new DefinitionProblem(DefinitionRule::Unimplemented, new Name('ROW variable naming in the REFERENCING clause')));
            }
        }
        if ($trigger->when !== null) {
            $scope = $trigger->row === true ? (new PseudoRelations())->scope($derivation, $trigger, $fact, false) : $derivation->environment();
            (new Conditions())->derive($derivation, $trigger->when, $scope, 'WHEN');
        }
    }

    /**
     * Derives a constraint trigger.
     */
    public function deriveConstraint(CreateConstraintTrigger $trigger, Derivation $derivation): void
    {
        $targets = new Targets();
        $fact = $derivation->target($trigger, $targets->resolve($derivation, $trigger->table));
        if ($trigger->referenced !== null) {
            $derivation->target($trigger->referenced, $targets->resolve($derivation, $trigger->referenced->name));
        }
        (new Attributes())->report($derivation, $trigger->attributes, 'TRIGGER', true, false, false);
        if ($trigger->when !== null) {
            (new Conditions())->derive($derivation, $trigger->when, (new PseudoRelations())->scope($derivation, $trigger, $fact, false), 'WHEN');
        }
    }

    /**
     * Reports an event or a filter variable the server does not know.
     */
    public function event(CreateEventTrigger $trigger, Derivation $derivation): void
    {
        if (!in_array($trigger->event->value, self::EVENTS, true)) {
            $derivation->report(new DefinitionProblem(DefinitionRule::EventName, $trigger->event));
        }
        foreach ($trigger->filters as $filter) {
            if ($filter->variable->value !== 'tag') {
                $derivation->report(new DefinitionProblem(DefinitionRule::FilterVariable, $filter->variable));
            }
        }
    }

    /**
     * Writes CREATE TRIGGER.
     */
    public function write(Output $out, CreateTrigger $trigger): void
    {
        $out->keyword('CREATE');
        if ($trigger->replace) {
            $out->keyword('OR', 'REPLACE');
        }
        $out->keyword('TRIGGER')->name($trigger->name)->keyword(...$trigger->timing->keywords());
        (new Writing())->separated($out, $trigger->events, 'OR');
        $out->keyword('ON');
        (new Spelling())->qualified($out, $trigger->table);
        if ($trigger->transitions !== []) {
            $out->keyword('REFERENCING');
            (new Writing())->sequence($out, $trigger->transitions);
        }
        if ($trigger->row !== null) {
            $out->keyword('FOR', 'EACH', $trigger->row ? 'ROW' : 'STATEMENT');
        }
        $this->tail($out, $trigger->when, $trigger->function, $trigger->arguments);
    }

    /**
     * Writes CREATE CONSTRAINT TRIGGER.
     */
    public function writeConstraint(Output $out, CreateConstraintTrigger $trigger): void
    {
        $out->keyword('CREATE');
        if ($trigger->replace) {
            $out->keyword('OR', 'REPLACE');
        }
        $out->keyword('CONSTRAINT', 'TRIGGER')->name($trigger->name)->keyword('AFTER');
        (new Writing())->separated($out, $trigger->events, 'OR');
        $out->keyword('ON');
        (new Spelling())->qualified($out, $trigger->table);
        if ($trigger->referenced !== null) {
            $out->keyword('FROM')->node($trigger->referenced);
        }
        (new Writing())->sequence($out, $trigger->attributes);
        $out->keyword('FOR', 'EACH', 'ROW');
        $this->tail($out, $trigger->when, $trigger->function, $trigger->arguments);
    }

    /**
     * Writes WHEN and EXECUTE FUNCTION with the arguments.
     *
     * @param list<TriggerArgument> $arguments
     */
    public function tail(Output $out, ?\SqlSemantics\Statement\Scalar $when, \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName $function, array $arguments): void
    {
        if ($when !== null) {
            $out->keyword('WHEN')->symbol('(')->node($when)->symbol(')');
        }
        $out->keyword('EXECUTE', 'FUNCTION')->node($function)->glue()->symbol('(')->list($arguments)->symbol(')');
    }
}
