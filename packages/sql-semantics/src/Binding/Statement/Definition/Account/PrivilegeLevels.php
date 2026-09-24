<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Account;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\Role\AccountNames;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\TableOccurrence;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Privilege\DatabaseScope;
use SqlSemantics\Model\Definition\Privilege\PrivilegeScope;
use SqlSemantics\Model\Definition\Privilege\RoutineTarget;
use SqlSemantics\Model\Query\Inspection\Routine\RoutineKind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Classifies the ON level of GRANT and REVOKE: global, database, table, or routine.
 * @visibility SqlSemantics
 */
final class PrivilegeLevels
{
    /**
     * Wildcard levels are scopes; a FUNCTION or PROCEDURE keyword requires a routine name; anything else is a table.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function read(Origin $origin, Node $statement, QueryContext $context): PrivilegeScope|DatabaseScope|TableReference|RoutineTarget
    {
        $level = Tree::child($statement, ['grant_ident']) ?? throw new UnclassifiedSql('A privilege level requires its ON target.');
        $kind = self::kind($statement);
        $tokens = $level->tokens();
        $words = array_map(static fn (Token $token): string => $token->text, $tokens);
        $scope = match (true) {
            $words === ['*'] => PrivilegeScope::CurrentDatabase,
            $words === ['*', '.', '*'] => PrivilegeScope::Global,
            count($words) === 3 && $words[2] === '*' => self::database($level, AccountNames::part($tokens[0], $context->tables->identifiers)),
            default => null,
        };
        if ($scope !== null) {
            if ($kind !== null) {
                throw new InvalidSql(InputViolation::PrivilegeLevel, $statement);
            }
            return $scope;
        }
        $parts = $context->tables->identifiers->parts($level);
        if ($kind !== null) {
            try {
                return new RoutineTarget(new QualifiedName($parts), $kind);
            } catch (InvalidStructure $error) {
                throw new InvalidSql(InputViolation::RoutineName, $level, $error);
            }
        }
        $declaration = $context->tables->resolve($parts, $level);
        $table = TableOccurrence::bind($context->ids->relation(), $origin->scopeId, $declaration, $context->tables->name($parts, $declaration), null, $level);
        if (!$table instanceof TableReference) {
            throw new UnclassifiedSql('A table privilege level requires a physical table occurrence.');
        }
        return $table;
    }

    /**
     * The routine class keyword after ON, or null for the table class.
     */
    public static function kind(Node $statement): ?RoutineKind
    {
        $children = Tree::significant($statement);
        foreach ($children as $index => $child) {
            if (!$child instanceof Token || strtoupper($child->text) !== 'ON') {
                continue;
            }
            $next = $children[$index + 1] ?? null;
            if ($next === null || ($next instanceof Node && $next->name !== 'opt_acl_type')) {
                return null;
            }
            return RoutineKind::tryFrom(strtoupper(Tree::text($next)));
        }
        return null;
    }

    /**
     * @throws InvalidSql
     */
    public static function database(Node $level, string $name): DatabaseScope
    {
        try {
            return new DatabaseScope($name);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::DatabaseName, $level, $error);
        }
    }
}
