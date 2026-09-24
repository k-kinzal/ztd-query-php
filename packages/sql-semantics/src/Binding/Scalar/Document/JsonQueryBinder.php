<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Document;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\Document\JsonOptions;
use SqlSemantics\Binding\Scope;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Document\JsonExistence;
use SqlSemantics\Model\Scalar\Document\JsonQueryExtraction;
use SqlSemantics\Model\Scalar\Document\JsonScalarExtraction;
use SqlSemantics\Model\TableFunction\Json\ArrayWrapping;
use SqlSemantics\Model\TableFunction\Json\Input;
use SqlSemantics\Model\TableFunction\Json\Quotes;
use SqlSemantics\Model\TableFunction\Json\Response\ValueBehavior;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds PostgreSQL's SQL/JSON query functions JSON_VALUE, JSON_QUERY and JSON_EXISTS with their document, path, PASSING and response clauses.
 * @visibility SqlSemantics
 */
final class JsonQueryBinder
{
    /**
     * Binds JSON_VALUE; its responses are ERROR, NULL or DEFAULT and its RETURNING has no FORMAT.
     * @throws InvalidSql
     */
    public static function value(Node $source, Scope $scope): JsonScalarExtraction
    {
        [$document, $path] = self::operands($source, $scope);
        $returning = SqlJsonClauses::returning($source, $scope);
        [$empty, $error] = JsonOptions::responses($source);
        $onEmpty = JsonOptions::response($empty, $scope);
        $onError = JsonOptions::response($error, $scope);
        foreach ([[$onEmpty, $empty], [$onError, $error]] as [$response, $node]) {
            if ($node !== null && ($response === ValueBehavior::EmptyArray || $response === ValueBehavior::EmptyObject)) {
                throw new InvalidSql(InputViolation::JsonOption, $node);
            }
        }
        if ($returning?->format !== null) {
            throw new InvalidSql(InputViolation::JsonOption, $source);
        }
        return new JsonScalarExtraction($source, $document->expression, $path, $returning?->type, $onEmpty, $onError, $document->format, SqlJsonClauses::passing($source, $scope));
    }

    /**
     * Binds JSON_QUERY; OMIT QUOTES cannot accompany WITH WRAPPER.
     * @throws InvalidSql
     */
    public static function query(Node $source, Scope $scope): JsonQueryExtraction
    {
        [$document, $path] = self::operands($source, $scope);
        $wrapper = JsonOptions::wrapper(Tree::child($source, ['json_wrapper_behavior']));
        $quotes = JsonOptions::quotes(Tree::child($source, ['json_quotes_clause_opt']));
        if (in_array($wrapper, [ArrayWrapping::Conditional, ArrayWrapping::Unconditional], true) && $quotes === Quotes::Omit) {
            throw new InvalidSql(InputViolation::JsonOption, $source);
        }
        [$empty, $error] = JsonOptions::responses($source);
        return new JsonQueryExtraction($source, $document, $path, SqlJsonClauses::passing($source, $scope), SqlJsonClauses::returning($source, $scope), $wrapper, $quotes, JsonOptions::response($empty, $scope), JsonOptions::response($error, $scope));
    }

    /**
     * Binds JSON_EXISTS; its only response is TRUE, FALSE, UNKNOWN or ERROR ON ERROR.
     * @throws InvalidSql
     */
    public static function exists(Node $source, Scope $scope): JsonExistence
    {
        [$document, $path] = self::operands($source, $scope);
        return new JsonExistence($source, $document, $path, SqlJsonClauses::passing($source, $scope), JsonOptions::exists(JsonOptions::responses($source)[1]));
    }

    /**
     * @return array{Input, Expression} The formatted document and the path expression
     */
    public static function operands(Node $source, Scope $scope): array
    {
        $document = Tree::child($source, ['json_value_expr']);
        $path = Tree::child($source, ['a_expr']);
        if ($document === null || $path === null) {
            Tree::invalid($source, 'SQL/JSON document and path');
        }
        return [JsonOptions::input($document, $scope), (new ExpressionBinder())->bind($path, $scope)];
    }
}
