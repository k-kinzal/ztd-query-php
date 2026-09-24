<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine\Stored;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\MySqlRemovals;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Routes MySQL stored procedure, stored function, trigger and event definitions to their binders.
 * @visibility SqlSemantics
 */
final class StoredPrograms
{
    /**
     * Returns null for statements outside the stored program family.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $statement, QueryContext $context): ?BoundStatement
    {
        if ($origin->dialect !== Dialect::MySql) {
            return null;
        }
        if ($statement->name === 'alter_event_stmt' || ($statement->name === 'alter' && array_filter($statement->children, static fn ($child): bool => $child instanceof Token && $child->name === 'EVENT_SYM') !== [])) {
            return EventDefinitions::alter($origin, $statement, $context, self::definer(Tree::child($statement, ['definer_opt']), $context));
        }
        $header = $statement->name === 'create' ? Tree::child($statement, ['view_or_trigger_or_sp_or_event']) : null;
        $tail = $header === null ? null : (Tree::outer($header, ['sp_tail', 'sf_tail', 'trigger_tail', 'event_tail'])[0] ?? null);
        if ($header === null || $tail === null) {
            return null;
        }
        $definer = self::definer(Tree::child($header, ['definer']), $context);
        return match ($tail->name) {
            'trigger_tail' => TriggerDefinitions::bind($origin, $tail, $context, $definer),
            'event_tail' => EventDefinitions::create($origin, $tail, $context, $definer),
            default => RoutineDefinitions::bind($origin, $tail, $context, $definer),
        };
    }

    /**
     * Reads the DEFINER account; an omitted definer is the account running the statement.
     */
    public static function definer(?Node $definer, QueryContext $context): AccountName|CurrentAccount|null
    {
        return $definer === null ? null : MySqlRemovals::accounts($definer, $context->tables->identifiers)[0];
    }

    /**
     * Reads a local or database-qualified program name.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function name(?Node $name, QueryContext $context): QualifiedName
    {
        $parts = $context->tables->identifiers->parts($name ?? throw new UnclassifiedSql('A stored program requires its name.'));
        if (in_array('', $parts, true)) {
            throw new InvalidSql(InputViolation::RoutineName, $name);
        }
        return new QualifiedName($parts);
    }
}
