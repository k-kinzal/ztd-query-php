<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Server;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Administration\MasterKeyScope;
use SqlSemantics\Model\Configuration\Administration\TlsChannel;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Server\Administration as Statement;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds ALTER INSTANCE actions, diagnosing identifiers the server does not accept.
 * @visibility SqlSemantics
 */
final class Instances
{
    /**
     * Reads the action from its keywords and identifier operands.
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $node, QueryContext $context): BoundStatement
    {
        $action = Tree::outer($node, ['alter_instance_action'])[0] ?? $node;
        $tokens = $action->tokens();
        $words = array_map(static fn ($token): string => strtoupper($token->text), $tokens);
        if (($words[0] ?? '') === 'ALTER') {
            $tokens = array_slice($tokens, 2);
            $words = array_slice($words, 2);
        }
        $names = array_map(static fn ($token): string => strtoupper(MySqlNames::read($token, $context->tables->identifiers)), $tokens);
        $release = ReplicationRelease::number($context->tables->schema->grammarVersion);
        return match ($words[0] ?? '') {
            'ROTATE' => new Statement\RotateMasterKeyStatement($origin, self::scope($names[1] ?? '', $release, $action)),
            'ENABLE', 'DISABLE' => ($names[1] ?? '') === 'INNODB' && ($names[2] ?? '') === 'REDO_LOG' ? new Statement\AlterRedoLogStatement($origin, $words[0] === 'ENABLE') : throw new InvalidSql(InputViolation::InstanceAction, $action),
            default => ($words[1] ?? '') === 'KEYRING' ? new Statement\ReloadKeyringStatement($origin) : self::tls($origin, $action, $context),
        };
    }

    /**
     * Accepts INNODB, and BINLOG from MySQL 8.0.
     * @throws InvalidSql
     */
    public static function scope(string $name, int $release, Node $action): MasterKeyScope
    {
        $scope = MasterKeyScope::tryFrom($name);
        if ($scope === null || ($scope === MasterKeyScope::BinaryLog && $release < 80000)) {
            throw new InvalidSql(InputViolation::InstanceAction, $action);
        }
        return $scope;
    }

    /**
     * Reads the optional channel and the NO ROLLBACK ON ERROR policy.
     * @throws InvalidSql
     */
    public static function tls(Origin $origin, Node $action, QueryContext $context): Statement\ReloadTlsStatement
    {
        $name = Tree::child($action, ['ident']);
        $channel = $name === null ? TlsChannel::Main : TlsChannel::tryFrom($context->tables->identifiers->name($name->tokens()[0]));
        if ($channel === null) {
            throw new InvalidSql(InputViolation::InstanceAction, $action);
        }
        $rollback = !in_array('ROLLBACK', array_map(static fn ($token): string => strtoupper($token->text), $action->tokens()), true);
        return new Statement\ReloadTlsStatement($origin, $channel, $rollback);
    }
}
