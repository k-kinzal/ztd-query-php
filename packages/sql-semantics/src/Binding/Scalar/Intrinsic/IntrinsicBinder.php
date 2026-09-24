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
        return self::temporal($source, $scope) ?? \SqlSemantics\Binding\Scalar\Text\TextOperations::bind($source, $scope) ?? CastBinder::bind($source, $scope) ?? JsonPathBinder::bind($source, $scope) ?? \SqlSemantics\Binding\Scalar\Document\SqlJsonBinder::bind($source, $scope) ?? TextLiteralBinder::bind($source, $scope) ?? TemporalLiteralBinder::bind($source, $scope) ?? ProposedColumnBinder::bind($source, $scope) ?? \SqlSemantics\Binding\Scalar\Collection\ArrayBinder::bind($source, $scope) ?? \SqlSemantics\Binding\Scalar\Collection\ArrayBinder::comparison($source, $scope) ?? \SqlSemantics\Binding\Scalar\Conditional\OverlapBinder::bind($source, $scope) ?? TypedLiteralBinder::bind($source, $scope);
    }

    /**
     * Resolves the temporal forms: interval arithmetic, TIMESTAMPADD and TIMESTAMPDIFF, EXTRACT, GET_FORMAT and time zone conversion.
     */
    public static function temporal(Node $source, Scope $scope): ?Expression
    {
        return DateShiftBinder::bind($source, $scope) ?? \SqlSemantics\Binding\Scalar\Temporal\TimestampBinder::bind($source, $scope) ?? ExtractBinder::bind($source, $scope) ?? TemporalFormatBinder::bind($source, $scope) ?? \SqlSemantics\Binding\Scalar\Temporal\ZoneBinder::bind($source, $scope);
    }
}
