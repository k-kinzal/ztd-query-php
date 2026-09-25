<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Text;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Scalar\Intrinsic\FullTextBinder;
use SqlSemantics\Binding\Scalar\Intrinsic\PositionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Expression;

/**
 * Dispatches the string operations written with keyword syntax: POSITION, TRIM, NORMALIZE, MATCH ... AGAINST, and
 * MySQL CHAR(... USING ...) and WEIGHT_STRING.
 * @visibility SqlSemantics
 */
final class TextOperations
{
    /**
     * Returns the bound string operation, or null when the node is not one of them.
     * @throws \SqlSemantics\InvalidSql
     */
    public static function bind(Node $source, Scope $scope): ?Expression
    {
        return PositionBinder::bind($source, $scope) ?? TrimBinder::bind($source, $scope) ?? NormalizationBinder::bind($source, $scope) ?? FullTextBinder::bind($source, $scope) ?? CodeFunctions::bind($source, $scope);
    }
}
