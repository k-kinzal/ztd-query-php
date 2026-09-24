<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Temporal;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Temporal\ZoneConversion;

/**
 * Binds PostgreSQL value AT TIME ZONE zone and value AT LOCAL.
 * @visibility SqlSemantics
 */
final class ZoneBinder
{
    /**
     * Recognizes the AT keyword between a value and TIME ZONE or LOCAL.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function bind(Node $source, Scope $scope): ?ZoneConversion
    {
        $children = $source->children;
        $at = $children[1] ?? null;
        if ($scope->identifiers->dialect !== Dialect::PostgreSql || !$at instanceof Token || strtoupper($at->text) !== 'AT' || !$children[0] instanceof Node) {
            return null;
        }
        $zone = end($children);
        $binder = new ExpressionBinder();
        return new ZoneConversion($source, $binder->bind($children[0], $scope), $zone instanceof Node ? $binder->bind($zone, $scope) : null);
    }
}
