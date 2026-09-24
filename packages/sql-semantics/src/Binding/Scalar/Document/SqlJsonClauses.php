<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Document;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\TypeReader;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\Document\JsonOptions;
use SqlSemantics\Binding\Query\Document\JsonTableBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Scalar\Document\Construction\JsonMember;
use SqlSemantics\Model\Scalar\Document\Construction\JsonNullHandling;
use SqlSemantics\Model\Scalar\Document\JsonReturning;
use SqlSemantics\Model\TableFunction\Json\PassingArgument;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Reads the clauses shared by PostgreSQL's SQL/JSON functions: RETURNING, PASSING, NULL handling, key uniqueness and object members.
 * @visibility SqlSemantics
 */
final class SqlJsonClauses
{
    /**
     * Reads the RETURNING type and its format; an encoding is only UTF8 for a bytea result.
     * @throws InvalidSql
     */
    public static function returning(Node $source, Scope $scope): ?JsonReturning
    {
        $clause = Tree::child($source, ['json_returning_clause_opt']);
        $type = $clause === null ? null : Tree::child($clause, ['Typename']);
        if ($clause === null || $type === null) {
            return null;
        }
        $format = Tree::child($clause, ['json_format_clause_opt']);
        $declared = (new TypeReader($scope->identifiers->dialect))->read($type);
        $encoding = JsonOptions::format($format === null ? null : Tree::child($format, ['json_format_clause']));
        if (!JsonReturning::accepts($declared, $encoding)) {
            throw new InvalidSql(InputViolation::JsonOption, $clause);
        }
        return new JsonReturning($declared, $encoding);
    }

    /**
     * @return list<PassingArgument> The PASSING variables in written order
     */
    public static function passing(Node $source, Scope $scope): array
    {
        $clause = Tree::child($source, ['json_passing_clause_opt']);
        return $clause === null ? [] : array_map(static fn (Node $argument): PassingArgument => JsonTableBinder::argument($argument, $scope), Tree::outer($clause, ['json_argument']));
    }

    /**
     * Reads NULL ON NULL or ABSENT ON NULL, falling back to the constructor's default.
     */
    public static function nullHandling(Node $source, JsonNullHandling $default): JsonNullHandling
    {
        $clause = Tree::child($source, ['json_object_constructor_null_clause_opt', 'json_array_constructor_null_clause_opt']);
        return $clause === null ? $default : JsonNullHandling::from(strtoupper(Tree::text($clause)));
    }

    /**
     * Whether WITH UNIQUE [KEYS] was written; WITHOUT UNIQUE [KEYS] is the default.
     */
    public static function uniqueKeys(Node $source): bool
    {
        $clause = Tree::child($source, ['json_key_uniqueness_constraint_opt']);
        return $clause !== null && str_starts_with(strtoupper(Tree::text($clause)), 'WITH ');
    }

    /**
     * Reads one `[KEY] key VALUE value` or `key : value` member.
     */
    public static function member(Node $source, Scope $scope): JsonMember
    {
        $key = Tree::child($source, ['c_expr', 'a_expr']);
        $value = Tree::child($source, ['json_value_expr']);
        if ($key === null || $value === null) {
            Tree::invalid($source, 'JSON object key and value');
        }
        return new JsonMember((new ExpressionBinder())->bind($key, $scope), JsonOptions::input($value, $scope));
    }
}
