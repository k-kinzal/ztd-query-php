<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\DefaultRequest;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\Between;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\PatternMatch;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\PatternOperator;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\BooleanTest;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\BooleanTestKind;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\JsonItemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\JsonTest;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\NormalizationTest;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\NullTest;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\NullTestSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\Overlaps;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the predicate productions of `a_expr`.
 *
 * Rule: PG-EXPRESSION-PREDICATE-001. Scope: LIKE, ILIKE and SIMILAR TO with
 * and without NOT and ESCAPE; IS [NOT] NULL, ISNULL, NOTNULL; IS [NOT]
 * TRUE, FALSE, UNKNOWN; [NOT] BETWEEN [ASYMMETRIC|SYMMETRIC]; IS [NOT]
 * [form] NORMALIZED; IS [NOT] JSON; OVERLAPS; DEFAULT; and, through
 * PG-EXPRESSION-SUBLINK-001, IN, the quantified comparisons and UNIQUE.
 * Also `opt_asymmetric` and `json_predicate_type_constraint`. Constructors:
 * `PatternMatch`, `NullTest`, `BooleanTest`, `Between`,
 * `NormalizationTest`, `JsonTest`, `Overlaps`, `DefaultRequest`.
 * Termination: each operand is a strictly smaller subtree.
 * Source: https://www.postgresql.org/docs/17/functions-comparison.html, https://www.postgresql.org/docs/17/functions-matching.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class PredicateRule
{
    /**
     * The pattern-matching productions: keyword, negated, position of the pattern, position of the escape or null.
     */
    private const PATTERNS = [
        'a_expr: a_expr LIKE a_expr' => [PatternOperator::Like, false, 2, null],
        'a_expr: a_expr LIKE a_expr ESCAPE a_expr' => [PatternOperator::Like, false, 2, 4],
        'a_expr: a_expr NOT_LA LIKE a_expr' => [PatternOperator::Like, true, 3, null],
        'a_expr: a_expr NOT_LA LIKE a_expr ESCAPE a_expr' => [PatternOperator::Like, true, 3, 5],
        'a_expr: a_expr ILIKE a_expr' => [PatternOperator::ILike, false, 2, null],
        'a_expr: a_expr ILIKE a_expr ESCAPE a_expr' => [PatternOperator::ILike, false, 2, 4],
        'a_expr: a_expr NOT_LA ILIKE a_expr' => [PatternOperator::ILike, true, 3, null],
        'a_expr: a_expr NOT_LA ILIKE a_expr ESCAPE a_expr' => [PatternOperator::ILike, true, 3, 5],
        'a_expr: a_expr SIMILAR TO a_expr' => [PatternOperator::SimilarTo, false, 3, null],
        'a_expr: a_expr SIMILAR TO a_expr ESCAPE a_expr' => [PatternOperator::SimilarTo, false, 3, 5],
        'a_expr: a_expr NOT_LA SIMILAR TO a_expr' => [PatternOperator::SimilarTo, true, 4, null],
        'a_expr: a_expr NOT_LA SIMILAR TO a_expr ESCAPE a_expr' => [PatternOperator::SimilarTo, true, 4, 6],
    ];

    /**
     * The truth-value test productions.
     */
    private const TRUTH = [
        'a_expr: a_expr IS TRUE_P' => BooleanTestKind::IsTrue, 'a_expr: a_expr IS NOT TRUE_P' => BooleanTestKind::IsNotTrue,
        'a_expr: a_expr IS FALSE_P' => BooleanTestKind::IsFalse, 'a_expr: a_expr IS NOT FALSE_P' => BooleanTestKind::IsNotFalse,
        'a_expr: a_expr IS UNKNOWN' => BooleanTestKind::IsUnknown, 'a_expr: a_expr IS NOT UNKNOWN' => BooleanTestKind::IsNotUnknown,
    ];

    /**
     * The NULL test productions: negated, spelling.
     */
    private const NULLS = [
        'a_expr: a_expr IS NULL_P' => [false, NullTestSpelling::Keywords], 'a_expr: a_expr IS NOT NULL_P' => [true, NullTestSpelling::Keywords],
        'a_expr: a_expr ISNULL' => [false, NullTestSpelling::Postfix], 'a_expr: a_expr NOTNULL' => [true, NullTestSpelling::Postfix],
    ];

    /**
     * The range test productions: negated, symmetric, position of the low bound.
     */
    private const RANGES = [
        'a_expr: a_expr BETWEEN opt_asymmetric b_expr AND a_expr' => [false, false, 3],
        'a_expr: a_expr NOT_LA BETWEEN opt_asymmetric b_expr AND a_expr' => [true, false, 4],
        'a_expr: a_expr BETWEEN SYMMETRIC b_expr AND a_expr' => [false, true, 3],
        'a_expr: a_expr NOT_LA BETWEEN SYMMETRIC b_expr AND a_expr' => [true, true, 4],
    ];

    /**
     * The JSON item kind of each `json_predicate_type_constraint` production.
     */
    private const JSON = [
        'json_predicate_type_constraint: JSON' => JsonItemKind::Json, 'json_predicate_type_constraint: JSON VALUE_P' => JsonItemKind::JsonValue,
        'json_predicate_type_constraint: JSON ARRAY' => JsonItemKind::JsonArray, 'json_predicate_type_constraint: JSON OBJECT_P' => JsonItemKind::JsonObject,
        'json_predicate_type_constraint: JSON SCALAR' => JsonItemKind::JsonScalar,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a predicate production, or answers null when the production is not one.
     */
    public function lower(Form $form): ?Scalar
    {
        $expressions = $this->lowering->expressions;
        $pattern = self::PATTERNS[$form->signature] ?? null;
        if ($pattern !== null) {
            [$operator, $negated, $right, $escape] = $pattern;

            return new PatternMatch(
                $expressions->expression($form->node(0)),
                $operator,
                $negated,
                $expressions->expression($form->node($right)),
                $escape === null ? null : $expressions->expression($form->node($escape)),
            );
        }
        $truth = self::TRUTH[$form->signature] ?? null;
        if ($truth !== null) {
            return new BooleanTest($expressions->expression($form->node(0)), $truth);
        }
        $null = self::NULLS[$form->signature] ?? null;
        if ($null !== null) {
            return new NullTest($expressions->expression($form->node(0)), $null[0], $null[1]);
        }

        return $this->range($form) ?? $this->tests($form) ?? (new SublinkRule($this->lowering))->lower($form);
    }

    /**
     * Lowers a range test, or answers null when the production is not one.
     */
    public function range(Form $form): ?Between
    {
        $range = self::RANGES[$form->signature] ?? null;
        if ($range === null) {
            return null;
        }
        [$negated, $symmetric, $low] = $range;
        if (!$symmetric) {
            $this->asymmetric($form->node($low - 1));
        }
        $expressions = $this->lowering->expressions;

        return new Between($expressions->expression($form->node(0)), $negated, $symmetric, $expressions->expression($form->node($low)), $expressions->expression($form->node($low + 2)));
    }

    /**
     * Accepts `opt_asymmetric`: ASYMMETRIC is the default and requests nothing.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function asymmetric(Node $flag): void
    {
        $form = $this->lowering->productions->form($flag);
        if ($form->signature !== 'opt_asymmetric: ASYMMETRIC' && $form->signature !== 'opt_asymmetric:') {
            throw ImplementationGap::production($form);
        }
    }

    /**
     * Lowers the NORMALIZED, JSON, OVERLAPS and DEFAULT productions, or answers null.
     */
    public function tests(Form $form): ?Scalar
    {
        $expressions = $this->lowering->expressions;

        return match ($form->signature) {
            'a_expr: a_expr IS NORMALIZED' => new NormalizationTest($expressions->expression($form->node(0)), false),
            'a_expr: a_expr IS NOT NORMALIZED' => new NormalizationTest($expressions->expression($form->node(0)), true),
            'a_expr: a_expr IS unicode_normal_form NORMALIZED' => new NormalizationTest($expressions->expression($form->node(0)), false, $this->lowering->flags->normalForm($form->node(2))),
            'a_expr: a_expr IS NOT unicode_normal_form NORMALIZED' => new NormalizationTest($expressions->expression($form->node(0)), true, $this->lowering->flags->normalForm($form->node(3))),
            'a_expr: a_expr IS json_predicate_type_constraint json_key_uniqueness_constraint_opt' => $this->json($form, false),
            'a_expr: a_expr IS NOT json_predicate_type_constraint json_key_uniqueness_constraint_opt' => $this->json($form, true),
            'a_expr: row OVERLAPS row' => new Overlaps($expressions->rowConstructor($form->node(0)), $expressions->rowConstructor($form->node(2))),
            'a_expr: DEFAULT' => new DefaultRequest(),
            default => null,
        };
    }

    /**
     * Lowers an IS JSON predicate.
     *
     * @throws ImplementationGap When the item kind has no rule
     */
    public function json(Form $form, bool $negated): JsonTest
    {
        $offset = $negated ? 3 : 2;
        $constraint = $this->lowering->productions->form($form->node($offset));
        $kind = self::JSON[$constraint->signature] ?? throw ImplementationGap::production($constraint);
        $uniqueness = $this->lowering->invocations->jsonKeyUniqueness($form->node($offset + 1));

        return new JsonTest($this->lowering->expressions->expression($form->node(0)), $negated, $kind, $uniqueness);
    }
}
