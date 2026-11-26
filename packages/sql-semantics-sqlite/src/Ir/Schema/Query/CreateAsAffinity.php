<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Query;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastTarget;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CollatedExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Concatenation;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryKind;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\BetweenTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;

/**
 * Derives native schema declarations from source-free expression roles.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class CreateAsAffinity
{
    /**
     * Finds the affinity of one source-free result expression.
     */
    public function affinity(object $value): ?CastTarget
    {
        while ($value instanceof CollatedExpression) {
            $value = $value->operand;
        }
        if ($value instanceof CastExpression) {
            return $value->target;
        }
        $this->dataTypes($value);

        return null;
    }

    /**
     * Maps a scalar affinity to its native materialized declaration.
     */
    public function declared(?CastTarget $affinity): CreatedColumnDeclaration
    {
        return match ($affinity) {
            CastTarget::Integer => CreatedColumnDeclaration::Integer,
            CastTarget::Numeric => CreatedColumnDeclaration::Numeric,
            CastTarget::Real => CreatedColumnDeclaration::Real,
            CastTarget::Text => CreatedColumnDeclaration::Text,
            CastTarget::Blob, null => CreatedColumnDeclaration::Untyped,
        };
    }

    /**
     * Combines affinities when SQLite keeps a compound VALUES body.
     *
     * @param non-empty-list<object> $operands The checked operands at one column position
     */
    public function compound(array $operands): CreatedColumnDeclaration
    {
        $affinity = null;
        $otherTypes = 0;
        foreach ($operands as $value) {
            $candidate = $this->affinity($value);
            if ($affinity === null && $candidate !== null) {
                $affinity = $candidate;
            } else {
                $otherTypes |= $this->dataTypes($value);
            }
        }
        $numeric = $affinity === CastTarget::Integer || $affinity === CastTarget::Numeric || $affinity === CastTarget::Real;
        if (($affinity === CastTarget::Text && ($otherTypes & 1) !== 0) || ($numeric && ($otherTypes & 2) !== 0)) {
            return CreatedColumnDeclaration::Untyped;
        }
        if ($numeric && $operands[0] instanceof CastExpression) {
            return CreatedColumnDeclaration::Numeric;
        }

        return $this->declared($affinity);
    }

    /**
     * Computes the native syntactic set of possible storage classes.
     *
     * Numeric, text and blob occupy bits 1, 2 and 4. NULL adds no bit.
     */
    public function dataTypes(object $value): int
    {
        while ($value instanceof CollatedExpression || ($value instanceof UnaryExpression && $value->kind === UnaryKind::Plus)) {
            $value = $value->operand;
        }
        if ($value instanceof CaseExpression) {
            return $this->caseTypes($value);
        }
        if ($value instanceof CastExpression) {
            return match ($value->target) {
                CastTarget::Integer, CastTarget::Numeric, CastTarget::Real => 5,
                CastTarget::Text => 6,
                CastTarget::Blob => 7,
            };
        }

        return $this->leafTypes($value);
    }

    /**
     * Classifies one terminal affinity role after handling the recursive forms.
     */
    public function leafTypes(object $value): int
    {
        if ($value instanceof NullLiteral) {
            return 0;
        }
        if ($value instanceof TextLiteral) {
            return 2;
        }
        if ($value instanceof BlobLiteral) {
            return 4;
        }
        if ($value instanceof Concatenation) {
            return 6;
        }
        if ($value instanceof Parameter || $value instanceof PatternTest) {
            return 7;
        }

        Check::input($value instanceof Arithmetic || $value instanceof BetweenTest || $value instanceof Comparison
            || $value instanceof FoldedMembership || $value instanceof HexIntegerLiteral || $value instanceof IntegerLiteral
            || $value instanceof Logical || $value instanceof MembershipTest || $value instanceof RealLiteral
            || $value instanceof TruthTest || $value instanceof UnaryExpression, 'CREATE TABLE AS requires an admitted source-free result operand.');

        return 1;
    }

    /**
     * Combines all possible result arms of a CASE.
     */
    public function caseTypes(CaseExpression $value): int
    {
        $types = $value->otherwise === null ? 0 : $this->dataTypes($value->otherwise);
        foreach ($value->branches as $branch) {
            $types |= $this->dataTypes($branch->then);
        }

        return $types;
    }
}
