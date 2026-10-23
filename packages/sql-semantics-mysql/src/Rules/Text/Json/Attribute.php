<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Text\Json;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * The shared domain of column, index, table and tablespace engine attributes.
 *
 * An empty quoted string resets the attribute. Every nonempty string denotes a
 * complete JSON value with finite numbers and at most 100 container levels.
 * These restrictions apply at definition time, including unreachable program
 * instructions, independently of storage state. Observed on MySQL 8.4.7.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Attribute
{
    /**
     * Enforces the common constructor invariant without inspecting execution state.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the operand cannot denote an engine attribute
     */
    public static function check(Text $attribute): void
    {
        Check::input($attribute->radix === null, 'An engine attribute requires quoted text.');
        $failure = self::failure($attribute->value);
        Check::input($failure === null, 'An engine attribute requires an empty string or a valid JSON document.' . ($failure === null ? '' : ' ' . $failure->reason));
    }

    /**
     * Answers a decoded operand's intrinsic refusal, retaining no SQL or parse tree.
     */
    public static function failure(string $text): ?Failure
    {
        if ($text === '') {
            return null;
        }
        try {
            (new Syntax($text))->check();
        } catch (Failure $failure) {
            return $failure;
        }
        return null;
    }
}
