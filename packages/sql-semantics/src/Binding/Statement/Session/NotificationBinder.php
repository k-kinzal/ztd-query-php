<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Session;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\ConstraintTiming;
use SqlSemantics\Model\Configuration\DiscardResource;
use SqlSemantics\Model\Statement\Configuration;
use SqlSemantics\Model\Statement\Notification;
use SqlSemantics\Model\Statement\Origin;

/**
 * Binds channel identities and closed session resource categories.
 * @visibility SqlSemantics
 */
final class NotificationBinder
{
    /**
     * Returns null when the statement belongs to a different operation family.
     */
    public static function bind(Origin $origin, Node $node, Scope $scope): ?BoundStatement
    {
        $tokens = $node->tokens();
        $verb = strtoupper($tokens[0]->text ?? '');
        if ($verb === 'LISTEN') {
            return new Notification\ListenStatement($origin, $scope->identifiers->name($tokens[1]));
        }
        if ($verb === 'UNLISTEN') {
            return $tokens[1]->text === '*' ? new Notification\UnlistenAllStatement($origin) : new Notification\UnlistenStatement($origin, $scope->identifiers->name($tokens[1]));
        }
        if ($verb === 'NOTIFY') {
            return new Notification\NotifyStatement($origin, $scope->identifiers->name($tokens[1]), count($tokens) > 2 ? SessionBinder::text($node, $scope) : null);
        }
        if ($verb === 'DISCARD') {
            $resource = strtoupper($tokens[1]->text);
            return new Configuration\DiscardStatement($origin, DiscardResource::from($resource === 'TEMP' ? 'TEMPORARY' : $resource));
        }
        if ($verb === 'SET' && strtoupper($tokens[1]->text ?? '') === 'CONSTRAINTS' && strtoupper($tokens[2]->text ?? '') === 'ALL') {
            return new Configuration\SetAllConstraintsStatement($origin, ConstraintTiming::from(strtoupper($tokens[3]->text)));
        }
        return null;
    }
}
