<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing;

use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\PatternOperator;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Types the pattern matches and the time zone conversions.
 *
 * Rule: PG-PREDICATE-TYPING-001. LIKE is the operator `~~`, ILIKE `~~*`
 * and SIMILAR TO the regular expression match `~` of the escaped pattern;
 * two string operands match as `text` (LIKE also as `character` and `name`
 * against `text`), and `bytea` matches `bytea` with LIKE; the result is
 * typed by PG-OPERATOR-TYPING-001. AT TIME ZONE and AT LOCAL call
 * `timezone`: `timestamp with time zone`, a date and an unknown-typed
 * constant (read as the preferred `timestamp with time zone`) give
 * `timestamp`, `timestamp` gives `timestamp with time zone`, a time gives
 * `time with time zone`; the zone is a string or an interval. Termination:
 * constant work.
 * Source: https://www.postgresql.org/docs/17/functions-matching.html,
 * https://www.postgresql.org/docs/17/functions-datetime.html#FUNCTIONS-DATETIME-ZONECONVERT. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class PredicateTyping
{
    /**
     * Types a pattern match: `boolean` for strings, and for `bytea` with LIKE.
     */
    public function pattern(AnalysisContext $context, PatternOperator $operator, TypeFact $left, TypeFact $right): TypeFact
    {
        $name = match ($operator) {
            PatternOperator::Like => '~~',
            PatternOperator::ILike => '~~*',
            PatternOperator::SimilarTo => '~',
        };
        $a = $this->string($left);
        $b = $this->string($right);
        if ($a !== null && $b !== null) {
            $left = new Known($operator === PatternOperator::Like && ($a === Builtin::Bpchar || $a === Builtin::Name) ? $a : Builtin::Text);
            $right = new Known(Builtin::Text);
        }

        return (new OperatorTyping())->named($context, new OperatorName(new Name($name)), $left, $right);
    }

    /**
     * Types a time zone conversion of a value by a zone.
     */
    public function zone(AnalysisContext $context, TypeFact $value, TypeFact $zone): TypeFact
    {
        foreach ([$value, $zone] as $operand) {
            if ($operand instanceof Invalid || $operand instanceof Dependent) {
                return $operand;
            }
        }
        $categories = new Categories();
        $time = $categories->builtin($value);
        $offset = $categories->builtin($zone);
        $zoned = $offset !== null && ($offset === Builtin::Unknown || $offset === Builtin::Interval || $categories->textual($offset));
        $result = match ($time) {
            Builtin::Timestamptz, Builtin::Unknown, Builtin::Date => Builtin::Timestamp,
            Builtin::Timestamp => Builtin::Timestamptz,
            Builtin::Time, Builtin::Timetz => Builtin::Timetz,
            default => null,
        };

        return $result === null || !$zoned || !(new OperatorTyping())->visible($context, 'timezone') ? (new OperatorTyping())->undeclared('timezone') : new Known($result);
    }

    /**
     * Answers the string type an operand matches as, unknown-typed constants and `varchar` as `text`; null for a type that is not a string.
     */
    public function string(TypeFact $operand): ?Builtin
    {
        $categories = new Categories();
        $type = $categories->builtin($operand);
        if ($type === Builtin::Unknown || $type === Builtin::Varchar) {
            return Builtin::Text;
        }

        return $type !== null && $categories->textual($type) ? $type : null;
    }
}
