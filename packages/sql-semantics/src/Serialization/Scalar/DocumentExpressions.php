<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Scalar;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Document\JsonScalarExtraction;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\TableFunction\Json\Input;
use SqlSemantics\Serialization\Document\JsonTables;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\TypeDeclaration;

/**
 * Writes JSON document operations from their named semantic operands.
 * @visibility SqlSemantics
 */
final class DocumentExpressions
{
    /**
     * Writes JSON_VALUE with its document format, PASSING variables, RETURNING type and non-default responses.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function write(JsonScalarExtraction $value): Tree
    {
        $mysql = $value->type->dialect === Dialect::MySql;
        return new Tree('json-value', [Build::keyword('JSON_VALUE'), Build::parentheses(new Tree('json-value-arguments', [
            JsonTables::input(new Input($value->document, $value->format)),
            new Atom('punctuation', ','),
            Expressions::write($value->path),
            SqlJsonExpressions::passing($value->passing),
            ...($value->returning === null ? [] : [Build::keyword('RETURNING'), $mysql ? CastTargets::write($value->returning) : TypeDeclaration::write($value->returning)]),
            JsonTables::response($value->onEmpty, 'EMPTY'),
            JsonTables::response($value->onError, 'ERROR'),
        ]))]);
    }
}
