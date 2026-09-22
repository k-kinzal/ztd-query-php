<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Intrinsic;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Expression;

/**
 * Classifies language operands before registered function invocation is considered.
 * @visibility SqlSemantics
 */
final class IntrinsicBinder
{
    /**
     * Resolves each language form through its dedicated operand binder.
     */
    public static function bind(Node $source, Scope $scope): ?Expression
    {
        $context = (new \SqlSemantics\Binding\Scalar\ContextValueBinder())->bind($source, $scope);
        if ($context !== null) {
            return $context;
        }
        if ($scope->identifiers->dialect === \SqlSemantics\Dialect::Sqlite && ($source->children[0] ?? null) instanceof \SqlParser\Lexer\Token && strtoupper($source->children[0]->text) === 'RAISE') {
            return \SqlSemantics\Binding\Scalar\RaiseBinder::bind($source, $scope);
        }
        return ExtractBinder::bind($source, $scope) ?? PositionBinder::bind($source, $scope) ?? TypedLiteralBinder::bind($source, $scope);
    }
}
