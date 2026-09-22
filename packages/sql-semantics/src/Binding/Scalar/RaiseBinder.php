<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Control;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Classifies trigger control flow without evaluating a message or running a trigger.
 * @visibility SqlSemantics
 */
final class RaiseBinder
{
    /**
     * @throws UnclassifiedSql
     */
    public static function bind(Node $source, Scope $scope): Expression
    {
        $facts = new ExpressionFacts(TypeDescriptor::builtin($scope->identifiers->dialect, 'never'), Nullability::NotNull);
        $action = Tree::child($source, ['raisetype']);
        if ($action === null && strtoupper($source->tokens()[2]->text ?? '') === 'IGNORE') {
            return new Control\RaiseIgnore($facts, $source);
        }
        $message = Tree::child($source, ['expr', 'nm']);
        if ($action === null || $message === null) {
            throw new UnclassifiedSql('RAISE requires a classified action and message.');
        }
        $value = (new ExpressionBinder())->bind($message, $scope);
        return new Control\RaiseError($facts, $source, Control\RaiseAction::from(strtoupper(Tree::text($action))), $value);
    }
}
