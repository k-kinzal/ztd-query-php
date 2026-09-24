<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Value\ContextReference;
use SqlSemantics\Model\Scalar\Value\ContextValueKind;
use SqlSemantics\Type\Nullability;

/**

 * Classifies clock/session requests and their optional declared precision. @visibility SqlSemantics

 */
final class ContextValueBinder
{
    /**
     * Classifies a session or clock request and its precision without obtaining its runtime value.
     */
    public function bind(Node $node, Scope $scope): ?ContextReference
    {
        $tokens = $node->tokens();
        $first = $tokens[0] ?? null;
        if ($first === null || in_array($first->name, ['IDENT', 'IDENT_QUOTED', 'ID'], true)) {
            return null;
        }
        $kind = ContextValueKind::tryFrom(strtoupper($first->text)) ?? (count($tokens) > 1 && $scope->identifiers->dialect === \SqlSemantics\Dialect::MySql ? self::synonym(strtoupper($first->text)) : null);
        if ($kind === null) {
            return null;
        }
        $precision = null;
        if (count($tokens) === 4 && $tokens[1]->text === '(' && ctype_digit($tokens[2]->text) && $tokens[3]->text === ')') {
            $precision = (int) $tokens[2]->text;
        } elseif (count($tokens) !== 1 && !(count($tokens) === 3 && $tokens[1]->text === '(' && $tokens[2]->text === ')')) {
            return null;
        }
        return new ContextReference(new ExpressionFacts(ContextResult::type($kind, $scope->identifiers->dialect, $precision), Nullability::NotNull), $node, $kind, $precision);
    }

    /**
     * Reads MySQL's function spellings of the standard current-time requests.
     */
    public static function synonym(string $name): ?ContextValueKind
    {
        return match ($name) {
            'NOW' => ContextValueKind::CurrentTimestamp,
            'CURDATE' => ContextValueKind::CurrentDate,
            'CURTIME' => ContextValueKind::CurrentTime,
            default => null,
        };
    }
}
