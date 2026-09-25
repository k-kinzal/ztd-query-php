<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Prepared;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\TypeReader;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scalar\VariableBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\StatementBinder;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Prepared as Statement;

/**
 * Distinguishes parsed query preparation from dynamic SQL supplied at execution time.
 * @visibility SqlSemantics
 */
final class PreparedBinder
{
    /**
     * Reads required query, source-text, argument, or deallocation operands.
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $node, QueryContext $context): ?BoundStatement
    {
        if (!in_array($node->name, ['PrepareStmt', 'ExecuteStmt', 'DeallocateStmt', 'prepare', 'execute', 'deallocate'], true)) {
            return null;
        }
        $nameNode = Tree::child($node, ['name', 'ident']);
        if ($node->name === 'DeallocateStmt' && $nameNode === null) {
            return new Statement\DeallocateAllStatement($origin);
        }
        $name = $nameNode === null ? throw new UnclassifiedSql('A prepared operation requires its name.') : $context->tables->identifiers->name($nameNode->tokens()[0]);
        if (in_array($node->name, ['DeallocateStmt', 'deallocate'], true)) {
            return new Statement\DeallocateStatement($origin, $name);
        }
        $scope = new Scope($context->tables->identifiers, queries: $context);
        if ($node->name === 'ExecuteStmt') {
            $table = TableFromExecute::bind($origin, $node, $name, $context);
            if ($table !== null) {
                return $table;
            }
            $arguments = array_map(static fn (Node $expression) => (new ExpressionBinder())->bind($expression, $scope), Tree::outer($node, ['a_expr']));
            return new Statement\ExecuteQueryStatement($origin, $name, $arguments);
        }
        if ($node->name === 'execute') {
            $variables = array_map(static fn (Node $variable) => (new VariableBinder())->bind($variable, $scope), Tree::outer($node, ['execute_var_ident']));
            return new Statement\ExecuteUsingStatement($origin, $name, $variables);
        }
        return $node->name === 'PrepareStmt' ? self::query($origin, $node, $name, $context) : self::text($origin, $node, $name, $scope);
    }

    /**
     * Binds each positional parameter using the declared static type.
     * @throws UnclassifiedSql
     */
    public static function query(Origin $origin, Node $node, string $name, QueryContext $context): Statement\PrepareQueryStatement
    {
        $types = array_map(static fn (Node $type) => (new TypeReader($origin->dialect))->read($type), Tree::outer(Tree::child($node, ['prep_type_clause']) ?? new Node('types', 0, []), ['Typename']));
        $query = Tree::child($node, ['PreparableStmt']) ?? throw new UnclassifiedSql('PREPARE AS requires a query.');
        $bound = (new StatementBinder($context->tables))->node($query, $query, new QueryContext($context->tables, $context->ids, parameterTypes: $types));
        if (!$bound instanceof \SqlSemantics\Model\BoundSelect && !$bound instanceof \SqlSemantics\Model\Statement\ValuesStatement && !$bound instanceof \SqlSemantics\Model\Statement\TableStatement && !$bound instanceof \SqlSemantics\Model\Statement\CompoundStatement && !$bound instanceof \SqlSemantics\Model\Statement\InsertStatement && !$bound instanceof \SqlSemantics\Model\Statement\UpdateStatement && !$bound instanceof \SqlSemantics\Model\Statement\DeleteStatement && !$bound instanceof \SqlSemantics\Model\Statement\MergeStatement) {
            throw new UnclassifiedSql('PREPARE AS requires an optimizable query.');
        }
        return new Statement\PrepareQueryStatement($origin, $name, $bound, $types);
    }

    /**
     * Retains the text-producing operand without parsing or evaluating its value.
     * @throws UnclassifiedSql
     */
    public static function text(Origin $origin, Node $node, string $name, Scope $scope): Statement\PrepareTextStatement
    {
        $source = Tree::child($node, ['prepare_src']) ?? throw new UnclassifiedSql('PREPARE FROM requires its source.');
        $token = $source->tokens()[0];
        $value = $token->text === '@' ? (new VariableBinder())->bind($source, $scope) : new \SqlSemantics\Model\Scalar\Value\Literal(new \SqlSemantics\Model\Scalar\ExpressionFacts(\SqlSemantics\Type\TypeDescriptor::builtin(Dialect::MySql, 'text'), \SqlSemantics\Type\Nullability::NotNull), $source, \SqlSemantics\Model\Scalar\Value\LiteralKind::Text, $token->text);
        return new Statement\PrepareTextStatement($origin, $name, $value);
    }
}
