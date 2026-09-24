<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Intrinsic;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Temporal\TemporalFormat;
use SqlSemantics\Model\Scalar\Temporal\TemporalFormatKind;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Binds MySQL GET_FORMAT with its temporal kind keyword and standard name.
 * @visibility SqlSemantics
 */
final class TemporalFormatBinder
{
    /**
     * Returns GET_FORMAT(kind, standard), or null for any other node.
     */
    public static function bind(Node $source, Scope $scope): ?TemporalFormat
    {
        $first = $source->children[0] ?? null;
        $kind = Tree::child($source, ['date_time_type']);
        $standard = Tree::child($source, ['expr']);
        if (!$first instanceof Token || strtoupper($first->text) !== 'GET_FORMAT' || $kind === null || $standard === null) {
            return null;
        }
        $word = strtoupper(Tree::text($kind));
        return new TemporalFormat(
            new ExpressionFacts(TypeDescriptor::builtin($scope->identifiers->dialect, 'varchar'), Nullability::MaybeNull),
            $source,
            $word === 'TIMESTAMP' ? TemporalFormatKind::Datetime : TemporalFormatKind::from($word),
            (new ExpressionBinder())->bind($standard, $scope),
        );
    }
}
