<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Session;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Server;

/**
 * Binds server actions with explicit connection, library and event operands.
 * @visibility SqlSemantics
 */
final class SessionBinder
{
    /**
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $node, Scope $scope): ?BoundStatement
    {
        $tokens = $node->tokens();
        $verb = strtoupper($tokens[0]->text ?? '');
        $simple = match ($verb) {
            'CHECKPOINT' => new Server\CheckpointStatement($origin),
            'RESTART' => new Server\RestartServerStatement($origin),
            'SHUTDOWN' => new Server\ShutdownServerStatement($origin),
            'UNLOCK' => new Server\UnlockTablesStatement($origin),
            default => null,
        };
        if ($simple !== null) {
            return $simple;
        }
        if ($verb === 'KILL') {
            $operand = Tree::outer($node, ['expr'])[0] ?? null;
            if ($operand === null) {
                throw new UnclassifiedSql('KILL requires a connection identifier expression.');
            }
            $value = (new ExpressionBinder())->bind($operand, $scope);
            return strtoupper($tokens[1]->text) === 'QUERY' ? new Server\KillQueryStatement($origin, $value) : new Server\KillConnectionStatement($origin, $value);
        }
        if (in_array($verb, ['INSTALL', 'UNINSTALL'], true) && strtoupper($tokens[1]->text ?? '') === 'PLUGIN') {
            $name = $scope->identifiers->name($tokens[2]);
            return $verb === 'INSTALL' ? new Server\InstallPluginStatement($origin, $name, self::text($node, $scope)) : new Server\UninstallPluginStatement($origin, $name);
        }
        if ($verb === 'CLONE' && strtoupper($tokens[1]->text ?? '') === 'LOCAL') {
            return new Server\CloneLocalStatement($origin, self::text($node, $scope));
        }
        if ($verb === 'BINLOG') {
            return new Server\ApplyBinlogStatement($origin, self::text($node, $scope));
        }
        return NotificationBinder::bind($origin, $node, $scope);
    }

    /**
     * Reads a required text-literal operand without evaluating or parsing its contents.
     * @throws UnclassifiedSql
     */
    public static function text(Node $node, Scope $scope): Literal
    {
        foreach ($node->tokens() as $token) {
            $value = (new LiteralBinder($scope->identifiers->dialect))->bind($token);
            if ($value instanceof Literal && $value->literalKind === LiteralKind::Text) {
                return $value;
            }
        }
        throw new UnclassifiedSql('A text-literal operand is required: ' . $node->toString());
    }
}
