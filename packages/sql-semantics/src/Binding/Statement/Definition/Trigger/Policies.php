<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Trigger;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\Role\RoleSpecs;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\PublicRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Relation\Policy\PolicyCommand;
use SqlSemantics\Model\Definition\Relation\Policy\PolicyMode;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Policy as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds CREATE POLICY and ALTER POLICY with expressions over the columns of the policy's table.
 * @visibility SqlSemantics
 */
final class Policies
{
    /**
     * Reads the shared operands and separates definition from alteration.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): Statement\CreatePolicyStatement|Statement\AlterPolicyStatement
    {
        $identifiers = $context->tables->identifiers;
        $name = $identifiers->name((Tree::child($source, ['name']) ?? throw new UnclassifiedSql('A policy requires its name.'))->tokens()[0]);
        $table = RelationTriggers::table(Tree::child($source, ['qualified_name']) ?? throw new UnclassifiedSql('A policy requires its table.'), $origin, $context);
        $using = self::expression(Tree::child($source, ['RowSecurityOptionalExpr']), $table, $context);
        $check = self::expression(Tree::child($source, ['RowSecurityOptionalWithCheck']), $table, $context);
        $roleList = Tree::outer($source, ['role_list'])[0] ?? null;
        $roles = $roleList === null ? null : array_map(static fn (Node $spec): NamedRole|SessionRole|PublicRole => RoleSpecs::grantee($spec, $identifiers), Tree::outer($roleList, ['RoleSpec']));
        try {
            if ($source->name === 'AlterPolicyStmt') {
                return new Statement\AlterPolicyStatement($origin, $name, $table, $roles, $using, $check);
            }
            $command = Tree::child($source, ['RowSecurityDefaultForCmd']);
            return new Statement\CreatePolicyStatement($origin, $name, $table, $roles ?? [PublicRole::Public], self::mode($source, $context), $command === null ? PolicyCommand::All : PolicyCommand::from(strtoupper($command->tokens()[1]->text)), $using, $check);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::RowSecurityPolicy, $source, $error);
        }
    }

    /**
     * AS names PERMISSIVE or RESTRICTIVE; any other word is rejected as PostgreSQL's parser does.
     * @throws InvalidSql
     */
    public static function mode(Node $source, QueryContext $context): PolicyMode
    {
        $clause = Tree::child($source, ['RowSecurityDefaultPermissive']);
        if ($clause === null) {
            return PolicyMode::Permissive;
        }
        return match ($context->tables->identifiers->name($clause->tokens()[1])) {
            'permissive' => PolicyMode::Permissive,
            'restrictive' => PolicyMode::Restrictive,
            default => throw new InvalidSql(InputViolation::RowSecurityPolicy, $clause),
        };
    }

    /**
     * Binds USING or WITH CHECK against the policy's table, or returns null when the clause is absent.
     * @throws UnclassifiedSql
     */
    public static function expression(?Node $clause, TableReference $table, QueryContext $context): ?Expression
    {
        if ($clause === null) {
            return null;
        }
        $expression = Tree::child($clause, ['a_expr']) ?? throw new UnclassifiedSql('A policy clause requires its expression.');
        return (new ExpressionBinder())->bind($expression, new Scope($context->tables->identifiers, [$table], queries: $context));
    }
}
