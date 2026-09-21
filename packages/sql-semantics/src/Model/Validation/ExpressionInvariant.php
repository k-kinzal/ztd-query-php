<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Validation;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;

/**
 * Enforces representation invariants at construction, independently of SQL validity diagnostics.
 *
 * @visibility SqlSemantics
 */
final class ExpressionInvariant
{
    /**
     * Rejects inconsistent reference states and mixed-dialect expression graphs.
     * @throws InvalidStructure
     */
    public static function check(Expression $expression): void
    {
        Collections::objects($expression->operands, Expression::class);
        Collections::strings($expression->reference);
        Collections::strings($expression->nullExtendedBy);
        if (($expression->kind === ExpressionKind::Column) !== ($expression->binding !== null)) {
            throw new InvalidStructure('A resolved column must have exactly one column binding.');
        }
        if ($expression->kind === ExpressionKind::UnresolvedColumn && ($expression->reference === [] || $expression->type->name !== 'unknown')) {
            throw new InvalidStructure('An unresolved column must retain its name and unknown type.');
        }
        if (in_array($expression->kind, [ExpressionKind::Literal, ExpressionKind::Parameter], true) && ($expression->symbol === null || ($expression->source instanceof \SqlParser\Lexer\Token ? $expression->source->text : implode('', array_map(static fn ($token): string => $token->text, $expression->source->tokens()))) !== $expression->symbol)) {
            throw new InvalidStructure('Literal and parameter spellings must agree with their source.');
        }
        foreach ($expression->operands as $operand) {
            if ($operand->type->dialect !== $expression->type->dialect) {
                throw new InvalidStructure('An expression cannot mix SQL dialects.');
            }
        }
        if ($expression->kind === ExpressionKind::Subquery && $expression->query === null) {
            throw new InvalidStructure('A subquery expression must retain its query.');
        }
        if (in_array($expression->kind, [ExpressionKind::Field, ExpressionKind::Subscript], true) && $expression->operands === []) {
            throw new InvalidStructure('Field and subscript access require an input.');
        }
        if ($expression->kind === ExpressionKind::DefaultValue && $expression->operands !== []) {
            throw new InvalidStructure('DEFAULT is a storage instruction without operands.');
        }
    }
}
