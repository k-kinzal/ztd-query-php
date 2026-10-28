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
 * These conditions are published on the complete definition, including unreachable
 * program instructions, independently of storage state. Observed on MySQL 8.4.7.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Attribute
{
    /**
     * Enforces quoted-text structure independently of JSON document validity.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the operand cannot denote an engine attribute
     */
    public static function check(Text $attribute): void
    {
        Check::input($attribute->radix === null, 'An engine attribute requires quoted text.');
    }

    /**
     * Publishes an invalid document as a condition on its complete enclosing option.
     */
    public static function derive(Text $attribute, \SqlSemantics\Construction\Derivation $derivation, \SqlSemantics\Statement\Node $owner): void
    {
        $failure = self::failure($attribute->value);
        if ($failure !== null) {
            $problem = new \SqlSemantics\Platform\MySql\Statement\Table\Problem\RefusedJsonAttribute($attribute, $failure->reason, $failure->position, $failure->deep);
            $derivation->report($problem);
            $derivation->warn(new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($problem, true), $owner);
        }
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
