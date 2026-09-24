<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Inspection;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\MySqlRemovals;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Query\Inspection\Filter\PatternFilter;
use SqlSemantics\Model\Query\Inspection\Routine\RoutineKind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Inspection\Definition;
use SqlSemantics\Model\Statement\Origin;

/**
 * Classifies SHOW CREATE requests and stored routine status and code listings.
 * @visibility SqlSemantics
 */
final class Definitions
{
    /**
     * Routes by the object keyword following SHOW CREATE, FUNCTION, or PROCEDURE.
     */
    public static function bind(Origin $origin, ShowRequest $request, QueryContext $context): ?BoundStatement
    {
        if ($request->word(0) === 'CREATE') {
            return self::create($origin, $request, $context);
        }
        if (!in_array($request->word(0), ['FUNCTION', 'PROCEDURE'], true)) {
            return null;
        }
        $routine = RoutineKind::from($request->word(0));
        return match ($request->word(1)) {
            'STATUS' => Filters::restrict(static fn (PatternFilter|ConditionFilter|null $filter): Definition\ShowRoutineStatusStatement => new Definition\ShowRoutineStatusStatement($origin, $routine, $filter), $request->form, $context),
            'CODE' => new Definition\ShowRoutineCodeStatement($origin, $routine, self::routineName($request->form, $context->tables->identifiers)),
            default => null,
        };
    }

    /**
     * Each described object kind keeps its own name domain: database, routine, table, or account.
     */
    public static function create(Origin $origin, ShowRequest $request, QueryContext $context): ?BoundStatement
    {
        $form = $request->form;
        $identifiers = $context->tables->identifiers;
        return match ($request->word(1)) {
            'DATABASE', 'SCHEMA' => new Definition\ShowCreateDatabaseStatement($origin, self::databaseName($form, $identifiers), Tree::child($form, ['opt_if_not_exists']) !== null),
            'EVENT' => new Definition\ShowCreateEventStatement($origin, self::routineName($form, $identifiers)),
            'FUNCTION' => new Definition\ShowCreateFunctionStatement($origin, self::routineName($form, $identifiers)),
            'PROCEDURE' => new Definition\ShowCreateProcedureStatement($origin, self::routineName($form, $identifiers)),
            'TRIGGER' => new Definition\ShowCreateTriggerStatement($origin, self::routineName($form, $identifiers)),
            'TABLE' => new Definition\ShowCreateTableStatement($origin, Filters::table($origin, $form, $context)),
            'VIEW' => new Definition\ShowCreateViewStatement($origin, Filters::table($origin, $form, $context)),
            'USER' => new Definition\ShowCreateUserStatement($origin, MySqlRemovals::accounts($form, $identifiers)[0]),
            default => null,
        };
    }

    /**
     * Reads an optionally database-qualified routine, trigger, or event name.
     */
    public static function routineName(Node $form, Identifiers $identifiers): QualifiedName
    {
        $name = Tree::child($form, ['sp_name']);
        if ($name === null) {
            Tree::invalid($form, 'routine name');
        }
        return new QualifiedName($identifiers->parts($name));
    }

    /**
     * Reads the single database identifier of SHOW CREATE DATABASE.
     */
    public static function databaseName(Node $form, Identifiers $identifiers): string
    {
        $name = Tree::child($form, ['ident']);
        if ($name === null) {
            Tree::invalid($form, 'database name');
        }
        $tokens = $name->tokens();
        return $identifiers->name($tokens[count($tokens) - 1]);
    }
}
