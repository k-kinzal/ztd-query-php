<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Maintenance as Statement;
use SqlSemantics\Model\Statement\Origin;

/**

 * Binds maintenance and database-attachment operands without resolving their runtime values. @visibility SqlSemantics

 */
final class MaintenanceBinder
{
    public function bind(Origin $origin, Node $node, Scope $scope): ?BoundStatement
    {
        $tokens = $node->tokens();
        $kind = strtoupper($tokens[0]->text ?? '');
        if ($kind === 'USE') {
            return new Statement\UseDatabaseStatement($origin, new QualifiedName($scope->identifiers->parts(new Node('database_name', 0, array_slice($tokens, 1)))));
        }
        if ($kind === 'REINDEX') {
            return Maintenance\ReindexBinder::bind($origin, $node, $scope);
        }
        if ($scope->identifiers->dialect !== \SqlSemantics\Dialect::Sqlite) {
            return null;
        }
        if ($kind === 'ANALYZE') {
            return count($tokens) === 1 ? new Statement\AnalyzeAllStatement($origin) : new Statement\AnalyzeNamedStatement($origin, new QualifiedName($scope->identifiers->parts(new Node('analyze_name', 0, array_slice($tokens, 1)))));
        }
        if ($kind === 'VACUUM') {
            $name = Tree::child($node, ['nm']);
            $schema = $name === null ? null : $scope->identifiers->parts($name)[0];
            $into = Tree::outer($node, ['expr'])[0] ?? null;
            return $into === null ? new Statement\VacuumDatabaseStatement($origin, $schema) : new Statement\VacuumIntoStatement($origin, (new ExpressionBinder())->bind($into, $scope), $schema);
        }
        if (in_array($kind, ['ATTACH', 'DETACH'], true)) {
            $arguments = array_map(static fn (Node $expression) => (new ExpressionBinder())->bind($expression, $scope), Tree::outer($node, ['expr']));
            if ($arguments === [] || ($kind === 'ATTACH' && !isset($arguments[1]))) {
                Tree::invalid($node, 'database attachment operands');
            }
            return $kind === 'ATTACH' ? new Statement\AttachDatabaseStatement($origin, $arguments[0], $arguments[1], $arguments[2] ?? null) : new Statement\DetachDatabaseStatement($origin, $arguments[0]);
        }
        return null;
    }
}
