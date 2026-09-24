<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Document;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\Document\JsonOptions;
use SqlSemantics\Binding\Scalar\Intrinsic\CastBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Scalar\Document\JsonScalarExtraction;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Retains the document, path, RETURNING type and ON EMPTY / ON ERROR responses of MySQL's JSON_VALUE.
 * @visibility SqlSemantics
 */
final class JsonValueBinder
{
    /**
     * Recognizes MySQL's JSON_VALUE keyword form.
     * @throws InvalidSql
     */
    public static function bind(Node $source, Scope $scope): ?JsonScalarExtraction
    {
        $first = $source->children[0] ?? null;
        if ($scope->identifiers->dialect !== Dialect::MySql || !$first instanceof Token || $first->name !== 'JSON_VALUE_SYM') {
            return null;
        }
        $document = Tree::child($source, ['simple_expr']);
        $pathNode = Tree::child($source, ['text_literal']);
        $path = $pathNode === null ? null : (new ExpressionBinder())->bind($pathNode, $scope);
        if ($document === null || !$path instanceof Literal) {
            throw new InvalidSql(InputViolation::FunctionArity, $source);
        }
        $returning = Tree::outer($source, ['opt_returning_type'])[0] ?? null;
        $target = $returning === null ? null : Tree::child($returning, ['cast_type']);
        $responses = Tree::child($source, ['opt_on_empty_or_error']);
        return new JsonScalarExtraction(
            $source,
            (new ExpressionBinder())->bind($document, $scope),
            $path,
            $target === null ? null : CastBinder::target($target),
            JsonOptions::response($responses === null ? null : (Tree::outer($responses, ['on_empty'])[0] ?? null), $scope),
            JsonOptions::response($responses === null ? null : (Tree::outer($responses, ['on_error'])[0] ?? null), $scope),
        );
    }
}
