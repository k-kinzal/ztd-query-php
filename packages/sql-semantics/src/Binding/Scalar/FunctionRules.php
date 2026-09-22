<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionRules;
use SqlSemantics\Binding\NullFacts;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Gives built-ins their result facts and preserves function calls with unknown signatures.
 *
 * @visibility SqlSemantics
 */
final class FunctionRules
{
    /**
     * @param list<Expression> $operands
     */
    public function bind(string $name, array $operands, Node $source, Scope $scope): Expression
    {
        if (in_array($name, ['COALESCE', 'NULLIF'], true)) {
            return (new ExpressionRules($scope->identifiers->dialect, $scope->diagnostics()))->call($name, $operands, $source);
        }
        $dialect = $scope->identifiers->dialect;
        $aggregate = in_array($name, ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'TOTAL', 'GROUP_CONCAT', 'STRING_AGG', 'ARRAY_AGG', 'JSON_AGG', 'JSONB_AGG', 'BOOL_AND', 'BOOL_OR', 'EVERY'], true);
        $window = Tree::outer($source, ['over_clause', 'windowing_clause']) !== [];
        $kind = $window ? ExpressionKind::Window : ($aggregate ? ExpressionKind::Aggregate : ExpressionKind::Function);
        $type = $this->type($name, $operands[0]->type ?? new TypeDescriptor($dialect, 'unknown'));
        $notNull = in_array($name, ['COUNT', 'ROW_NUMBER', 'RANK', 'DENSE_RANK', 'NTILE', 'CURRENT_DATE', 'CURRENT_TIMESTAMP', 'RANDOM', 'RAND', 'TOTAL'], true);
        $strict = in_array($name, ['LOWER', 'UPPER', 'LENGTH', 'CHAR_LENGTH', 'ABS', 'ROUND', 'TRIM', 'LTRIM', 'RTRIM'], true);
        $nullable = $notNull ? Nullability::NotNull : ($strict ? NullFacts::strict($operands) : ($aggregate ? Nullability::MaybeNull : Nullability::Unknown));
        return new Expression($kind, $type, $nullable, $source, $operands, symbol: $name, nullExtendedBy: NullFacts::extensions($operands, $nullable));
    }

    /**
     * Resolves common built-ins without guessing user-defined function signatures.
     */
    public function type(string $name, TypeDescriptor $input): TypeDescriptor
    {
        $dialect = $input->dialect;
        $type = match ($name) {
            'COUNT', 'ROW_NUMBER', 'RANK', 'DENSE_RANK', 'NTILE' => $dialect === Dialect::Sqlite ? 'integer' : 'bigint',
            'LOWER', 'UPPER', 'TRIM', 'LTRIM', 'RTRIM', 'CONCAT', 'CONCAT_WS', 'SUBSTR', 'SUBSTRING', 'REPLACE', 'STRING_AGG', 'GROUP_CONCAT' => 'text',
            'LENGTH', 'CHAR_LENGTH', 'CHARACTER_LENGTH' => 'integer',
            'GENERATE_SERIES', 'UNNEST', 'MIN', 'MAX', 'ABS', 'ROUND', 'LAG', 'LEAD', 'FIRST_VALUE', 'LAST_VALUE', 'NTH_VALUE' => $input->name,
            'AVG', 'SUM' => $this->numericAggregate($name, $input),

            'TOTAL', 'RAND', 'PERCENT_RANK', 'CUME_DIST' => 'double precision',
            'BOOL_AND', 'BOOL_OR', 'EVERY' => 'boolean',
            'JSON_AGG' => 'json',
            'JSONB_AGG' => 'jsonb',
            'ARRAY_AGG' => $input->name . '[]',
            'CURRENT_DATE' => 'date',
            'CURRENT_TIMESTAMP', 'NOW' => 'timestamp',
            default => 'unknown',
        };
        return new TypeDescriptor($dialect, $type);
    }
    /**
     * Resolves aggregate promotion independently from scalar function rules.
     */
    public function numericAggregate(string $name, TypeDescriptor $input): string
    {
        $dialect = $input->dialect;
        if ($name === 'AVG') {
            return $dialect === Dialect::Sqlite ? 'real' : (in_array($input->name, ['real', 'double precision'], true) ? 'double precision' : 'numeric');
        }
        return $dialect === Dialect::Sqlite ? 'dynamic' : ($dialect === Dialect::PostgreSql && in_array($input->name, ['smallint', 'integer'], true) ? 'bigint' : (in_array($input->name, ['real', 'double precision'], true) ? $input->name : 'numeric'));
    }

}
