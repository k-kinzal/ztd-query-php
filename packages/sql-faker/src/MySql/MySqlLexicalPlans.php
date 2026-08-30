<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use SqlFaker\Grammar\Walk\GenerationPlan;

/**
 * Names the plans that ask for a lexeme rather than for a derivation.
 *
 * A lexical plan names a target in the dialect's lexical grammar and the
 * lengths it may be written at, so it is answered by generating a lexeme
 * directly instead of by walking the parser grammar. That is a different way
 * of arriving at SQL, and the plans that take it are named together here.
 */
final class MySqlLexicalPlans
{
    /**
     * @return GenerationPlan<true>
     */
    public static function quotedIdentifier(int $minLength, int $maxLength): GenerationPlan
    {
        return GenerationPlan::lexical('quoted_identifier', compact('minLength', 'maxLength'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function stringLiteral(int $minLength, int $maxLength): GenerationPlan
    {
        return GenerationPlan::lexical('string_literal', compact('minLength', 'maxLength'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function nationalStringLiteral(int $minLength, int $maxLength): GenerationPlan
    {
        return GenerationPlan::lexical('national_string_literal', compact('minLength', 'maxLength'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function dollarQuotedString(int $minLength, int $maxLength): GenerationPlan
    {
        return GenerationPlan::lexical('dollar_quoted_string', compact('minLength', 'maxLength'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function integerLiteral(int $min, int $max): GenerationPlan
    {
        return GenerationPlan::lexical('integer_literal', compact('min', 'max'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function longIntegerLiteral(int $min, int $max): GenerationPlan
    {
        return GenerationPlan::lexical('long_integer_literal', compact('min', 'max'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function unsignedBigIntLiteral(int $minLength, int $maxLength): GenerationPlan
    {
        return GenerationPlan::lexical('unsigned_big_int_literal', compact('minLength', 'maxLength'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function decimalLiteral(int $precision, int $scale): GenerationPlan
    {
        return GenerationPlan::lexical('decimal_literal', compact('precision', 'scale'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function floatLiteral(
        int $precision,
        int $scale,
        int $minExponent,
        int $maxExponent,
    ): GenerationPlan {
        return GenerationPlan::lexical(
            'float_literal',
            compact('precision', 'scale', 'minExponent', 'maxExponent'),
        );
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function hexLiteral(int $minLength, int $maxLength): GenerationPlan
    {
        return GenerationPlan::lexical('hex_literal', compact('minLength', 'maxLength'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function quotedHexLiteral(int $minBytes, int $maxBytes): GenerationPlan
    {
        return GenerationPlan::lexical('quoted_hex_literal', compact('minBytes', 'maxBytes'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function binaryLiteral(int $minLength, int $maxLength): GenerationPlan
    {
        return GenerationPlan::lexical('binary_literal', compact('minLength', 'maxLength'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function hostname(int $minParts, int $maxParts, int $maxPartLength): GenerationPlan
    {
        return GenerationPlan::lexical('hostname', compact('minParts', 'maxParts', 'maxPartLength'));
    }
}
