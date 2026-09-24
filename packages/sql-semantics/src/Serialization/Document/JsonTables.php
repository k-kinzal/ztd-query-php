<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Document;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\TableFunction\Json;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\TypeDeclaration;

/**
 * Writes JSON row and column paths, conversion policies, and nested declarations.
 * @visibility SqlSemantics
 */
final class JsonTables
{
    /**
     * Serializes the complete JSON_TABLE operation from semantic operands.
     */
    public static function write(Json\JsonTable $table, Dialect $dialect): Tree
    {
        $parts = [self::input($table->document), new Atom('punctuation', ','), Expressions::write($table->path)];
        if ($table->pathName !== null) {
            array_push($parts, Build::keyword('AS'), Build::identifier([$table->pathName], $dialect));
        }
        if ($table->passing !== []) {
            array_push($parts, Build::keyword('PASSING'), Build::separated(array_map(static fn (Json\PassingArgument $argument): Tree => new Tree('passing', [self::input($argument->input), Build::keyword('AS'), Build::identifier([$argument->name], $dialect)]), $table->passing)));
        }
        array_push($parts, Build::keyword('COLUMNS'), Build::parentheses(Build::separated(array_map(static fn (Json\Column $column): Tree => self::column($column, $dialect), $table->columns))));
        if ($table->onError !== Json\Response\TableError::Default) {
            $parts[] = Build::keyword($table->onError->value . ' ON ERROR');
        }
        return new Tree('json-table', [Build::keyword('JSON_TABLE'), Build::parentheses(new Tree('document-rows', $parts))]);
    }

    /**
     * Keeps an explicit document format attached to its own expression.
     */
    public static function input(Json\Input $input): Tree
    {
        return new Tree('json-input', [Expressions::write($input->expression), ...($input->format === null ? [] : [Build::keyword($input->format->value)])]);
    }

    /**
     * Writes each column form with only its applicable clauses.
     * @throws InvalidStructure
     */
    public static function column(Json\Column $column, Dialect $dialect): Tree
    {
        if ($column instanceof Json\NestedColumns) {
            return new Tree('nested-json-columns', [Build::keyword('NESTED PATH'), Expressions::write($column->path), ...($column->name === null ? [] : [Build::keyword('AS'), Build::identifier([$column->name], $dialect)]), Build::keyword('COLUMNS'), Build::parentheses(Build::separated(array_map(static fn (Json\Column $child): Tree => self::column($child, $dialect), $column->columns)))]);
        }
        if ($column instanceof Json\Ordinality) {
            return new Tree('ordinality', [Build::identifier([$column->name], $dialect), Build::keyword('FOR ORDINALITY')]);
        }
        if (!$column instanceof Json\ValueColumn && !$column instanceof Json\ExistsColumn) {
            throw new InvalidStructure('Unclassified JSON_TABLE column.');
        }
        $parts = [Build::identifier([$column->name], $dialect), TypeDeclaration::write($column->type)];
        if ($column->collation !== null) {
            array_push($parts, Build::keyword('COLLATE'), Build::identifier($column->collation->parts, $dialect));
        }
        if ($column instanceof Json\ExistsColumn) {
            $parts[] = Build::keyword('EXISTS');
        } elseif ($column->format !== null) {
            $parts[] = Build::keyword($column->format->value);
        }
        if ($column->path !== null) {
            array_push($parts, Build::keyword('PATH'), Expressions::write($column->path));
        }
        if ($column instanceof Json\ExistsColumn) {
            array_push($parts, ...[...($column->onError === Json\Response\ExistsResponse::Default ? [] : [Build::keyword($column->onError->value . ' ON ERROR')])]);
        } else {
            array_push($parts, ...[...($column->wrapper === Json\ArrayWrapping::Default ? [] : [Build::keyword($column->wrapper->value)]), ...($column->quotes === Json\Quotes::Default ? [] : [Build::keyword($column->quotes->value)]), self::response($column->onEmpty, 'EMPTY'), self::response($column->onError, 'ERROR')]);
        }
        return new Tree('json-column', $parts);
    }

    /**
     * The response kind determines whether a default expression is required; a signed literal default is written bare, as MySQL's signed_literal requires.
     * @throws InvalidStructure
     */
    public static function response(Json\Response\ValueResponse $response, string $condition): Tree
    {
        if ($response === Json\Response\ValueBehavior::Default) {
            return new Tree('default-behavior', []);
        }
        $value = $response instanceof Json\Response\DefaultResponse ? $response->expression : null;
        if ($value instanceof \SqlSemantics\Model\Scalar\Operator\UnaryExpression && $value->operand instanceof \SqlSemantics\Model\Scalar\Value\Literal && in_array($value->operator->value, ['-', '+'], true)) {
            return new Tree('json-response', [Build::keyword('DEFAULT'), new Tree('signed-literal', [Build::keyword($value->operator->value), Expressions::write($value->operand)]), Build::keyword('ON ' . $condition)]);
        }
        return new Tree('json-response', [...($value !== null ? [Build::keyword('DEFAULT'), Expressions::write($value)] : [$response instanceof Json\Response\ValueBehavior ? Build::keyword($response->value) : throw new InvalidStructure('Unclassified JSON value response.')]), Build::keyword('ON ' . $condition)]);
    }
}
