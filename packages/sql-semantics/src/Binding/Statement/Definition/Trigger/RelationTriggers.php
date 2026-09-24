<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Trigger;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\TableOccurrence;
use SqlSemantics\Binding\Scalar\Intrinsic\FieldSpelling;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\Relation\ConstraintActions;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Trigger as Operand;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Relation\TriggerRow;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Trigger\RowVersion;
use SqlSemantics\Model\Trigger\Timing;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds CREATE TRIGGER and CREATE CONSTRAINT TRIGGER on a table with the row images their condition may read.
 * @visibility SqlSemantics
 */
final class RelationTriggers
{
    /**
     * Separates ordinary triggers from deferrable constraint triggers.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): Statement\CreateTriggerStatement|Statement\CreateConstraintTriggerStatement
    {
        $identifiers = $context->tables->identifiers;
        $name = $identifiers->name((Tree::child($source, ['name']) ?? throw new UnclassifiedSql('A trigger requires its name.'))->tokens()[0]);
        $table = self::table(Tree::child($source, ['qualified_name']) ?? throw new UnclassifiedSql('A trigger requires its table.'), $origin, $context);
        $events = self::events(Tree::child($source, ['TriggerEvents']) ?? throw new UnclassifiedSql('A trigger requires its events.'), $context);
        $invocation = self::invocation($source, $context);
        $constraint = in_array('CONSTRAINT', array_map(static fn (Token $token): string => strtoupper($token->text), array_slice($source->tokens(), 1, 3)), true);
        $type = Tree::outer($source, ['TriggerForType'])[0] ?? null;
        $level = $constraint || ($type !== null && strtoupper(Tree::text($type)) === 'ROW') ? Operand\TriggerLevel::Row : Operand\TriggerLevel::Statement;
        $when = Tree::child($source, ['TriggerWhen']);
        $condition = $when === null ? null : self::condition($when, $table, Operand\RelationTriggerInvariant::images($events, $level), $context);
        try {
            if ($constraint) {
                return self::constraint($origin, $source, $name, $table, $events, $invocation, $condition, $context);
            }
            $timing = Tree::child($source, ['TriggerActionTime']) ?? throw new UnclassifiedSql('A trigger requires its timing.');
            return new Statement\CreateTriggerStatement($origin, $name, $table, Timing::from(strtoupper(Tree::text($timing))), $events, $level, $invocation, self::transitions($source, $context), $condition, Tree::child($source, ['opt_or_replace']) !== null);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::TriggerDefinition, $source, $error);
        }
    }

    /**
     * A constraint trigger cannot be replaced, and its attributes only choose when the check runs.
     * @throws InvalidSql
     * @throws InvalidStructure
     * @throws UnclassifiedSql
     */
    public static function constraint(Origin $origin, Node $source, string $name, TableReference $table, Operand\TriggerEvents $events, Operand\TriggerInvocation $invocation, ?Expression $condition, QueryContext $context): Statement\CreateConstraintTriggerStatement
    {
        if (Tree::child($source, ['opt_or_replace']) !== null) {
            throw new InvalidSql(InputViolation::TriggerDefinition, $source);
        }
        $attributes = array_map(static fn (Node $element): string => strtoupper(Tree::text($element)), Tree::outer($source, ['ConstraintAttributeElem']));
        if (array_diff($attributes, ['DEFERRABLE', 'NOT DEFERRABLE', 'INITIALLY DEFERRED', 'INITIALLY IMMEDIATE']) !== [] || (in_array('INITIALLY DEFERRED', $attributes, true) && in_array('INITIALLY IMMEDIATE', $attributes, true))) {
            throw new InvalidSql(InputViolation::ConstraintAttribute, $source);
        }
        $from = Tree::child($source, ['OptConstrFromTable']);
        $referenced = $from === null ? null : self::table(Tree::child($from, ['qualified_name']) ?? throw new UnclassifiedSql('FROM requires the referenced table.'), $origin, $context);
        return new Statement\CreateConstraintTriggerStatement($origin, $name, $table, $events, $invocation, ConstraintActions::checking($attributes, $source), $referenced, $condition);
    }

    /**
     * Resolves the named table without reading it; an unknown table stays a diagnosed unresolved declaration.
     * @throws UnclassifiedSql
     */
    public static function table(Node $name, Origin $origin, QueryContext $context): TableReference
    {
        $table = TableOccurrence::resolve($name, $context, $origin->scopeId);
        if (!$table instanceof TableReference) {
            throw new UnclassifiedSql('A trigger table cannot exclude descendants.');
        }
        return $table;
    }

    /**
     * Reads each event once; repeating an event is rejected as PostgreSQL's parser does.
     * @throws InvalidSql
     */
    public static function events(Node $source, QueryContext $context): Operand\TriggerEvents
    {
        $events = [];
        $columns = [];
        foreach (Tree::outer($source, ['TriggerOneEvent']) as $event) {
            $events[] = Operand\TriggerEvent::from(strtoupper($event->tokens()[0]->text));
            foreach (Tree::outer($event, ['columnElem']) as $column) {
                $columns[] = $context->tables->identifiers->name($column->tokens()[0]);
            }
        }
        try {
            return new Operand\TriggerEvents($events, $columns);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::TriggerDefinition, $source, $error);
        }
    }

    /**
     * Reads the executed function and the text each argument constant passes to it.
     * @throws UnclassifiedSql
     */
    public static function invocation(Node $source, QueryContext $context): Operand\TriggerInvocation
    {
        $function = Tree::child($source, ['func_name']) ?? throw new UnclassifiedSql('A trigger requires its function.');
        $arguments = array_map(static fn (Node $argument): string => self::argument($argument, $context), Tree::outer($source, ['TriggerFuncArg']));
        return new Operand\TriggerInvocation(new QualifiedName($context->tables->identifiers->parts($function)), $arguments);
    }

    /**
     * An integer passes its decimal value, a numeric its spelling, a string its content, and a label its name.
     */
    public static function argument(Node $argument, QueryContext $context): string
    {
        $token = $argument->tokens()[0];
        $kind = Tree::child($argument, ['Iconst', 'Sconst', 'ColLabel'])?->name;
        if ($kind === 'Sconst') {
            return FieldSpelling::read($token, $context->tables->identifiers);
        }
        if ($kind === 'ColLabel') {
            return $context->tables->identifiers->name($token);
        }
        if ($kind !== 'Iconst') {
            return $token->text;
        }
        $digits = str_replace('_', '', $token->text);
        $base = match (strtolower(substr($digits, 0, 2))) {
            '0x' => 16,
            '0o' => 8,
            '0b' => 2,
            default => 10,
        };
        return (string) intval($base === 10 ? $digits : substr($digits, 2), $base);
    }

    /**
     * Reads the REFERENCING names; a ROW transition is rejected because PostgreSQL supports only tables.
     * @return list<Operand\TransitionTable>
     * @throws InvalidSql
     * @throws InvalidStructure
     */
    public static function transitions(Node $source, QueryContext $context): array
    {
        $transitions = [];
        foreach (Tree::outer($source, ['TriggerTransition']) as $transition) {
            $words = array_map(static fn (Token $token): string => strtoupper($token->text), $transition->tokens());
            if ($words[1] !== 'TABLE') {
                throw new InvalidSql(InputViolation::TriggerDefinition, $transition);
            }
            $name = Tree::child($transition, ['TransitionRelName']) ?? throw new InvalidStructure('A transition table requires its name.');
            $transitions[] = new Operand\TransitionTable($words[0] === 'OLD' ? RowVersion::Old : RowVersion::New, $context->tables->identifiers->name($name->tokens()[0]));
        }
        return $transitions;
    }

    /**
     * Binds WHEN with only the row images the events and granularity supply.
     * @param list<RowVersion> $images
     * @throws UnclassifiedSql
     */
    public static function condition(Node $when, TableReference $table, array $images, QueryContext $context): Expression
    {
        $rows = array_map(static fn (RowVersion $version): TriggerRow => new TriggerRow($context->ids->relation(), $table->scopeId, $table->declaration, $table->source, $version), $images);
        $expression = Tree::child($when, ['a_expr']) ?? throw new UnclassifiedSql('WHEN requires its condition.');
        return (new ExpressionBinder())->bind($expression, new Scope($context->tables->identifiers, $rows, queries: $context));
    }
}
