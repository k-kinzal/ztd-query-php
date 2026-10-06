<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Invocation;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehavior;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehaviorClause;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehaviorKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonFunctionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonQueryFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonQuoting;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonWrapperKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonWrapping;

/**
 * Lowers the SQL/JSON query functions of PostgreSQL 17 and their clauses.
 *
 * Rule: PG-JSON-QUERY-LOWERING-001. Scope: the JSON_QUERY, JSON_EXISTS and
 * JSON_VALUE productions of `func_expr_common_subexpr`,
 * `json_passing_clause_opt`, `json_arguments`, `json_argument`,
 * `json_wrapper_behavior`, `json_behavior`, `json_behavior_type`,
 * `json_behavior_clause_opt`, `json_on_error_clause_opt`,
 * `json_quotes_clause_opt`. Constructors: the classes of
 * `Statement/Invocation/Json/Query`. UNCONDITIONAL and ARRAY in the wrapper clause and ON
 * SCALAR STRING in the quotes clause are noise (see `InvocationNoise`).
 * Termination: lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-QUERYING. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class JsonQueryRule
{
    /**
     * The wrapper clauses by production signature.
     */
    private const WRAPPERS = [
        'json_wrapper_behavior:' => null,
        'json_wrapper_behavior: WITHOUT WRAPPER' => JsonWrapperKind::Without,
        'json_wrapper_behavior: WITHOUT ARRAY WRAPPER' => JsonWrapperKind::Without,
        'json_wrapper_behavior: WITH WRAPPER' => JsonWrapperKind::Unconditional,
        'json_wrapper_behavior: WITH ARRAY WRAPPER' => JsonWrapperKind::Unconditional,
        'json_wrapper_behavior: WITH CONDITIONAL ARRAY WRAPPER' => JsonWrapperKind::Conditional,
        'json_wrapper_behavior: WITH CONDITIONAL WRAPPER' => JsonWrapperKind::Conditional,
        'json_wrapper_behavior: WITH UNCONDITIONAL ARRAY WRAPPER' => JsonWrapperKind::Unconditional,
        'json_wrapper_behavior: WITH UNCONDITIONAL WRAPPER' => JsonWrapperKind::Unconditional,
    ];

    /**
     * The quotes clauses by production signature.
     */
    private const QUOTES = [
        'json_quotes_clause_opt:' => null,
        'json_quotes_clause_opt: KEEP QUOTES ON SCALAR STRING_P' => true,
        'json_quotes_clause_opt: KEEP QUOTES' => true,
        'json_quotes_clause_opt: OMIT QUOTES ON SCALAR STRING_P' => false,
        'json_quotes_clause_opt: OMIT QUOTES' => false,
    ];

    /**
     * The fixed behaviors by production signature.
     */
    private const BEHAVIORS = [
        'json_behavior_type: ERROR_P' => JsonBehaviorKind::Error,
        'json_behavior_type: NULL_P' => JsonBehaviorKind::Null,
        'json_behavior_type: TRUE_P' => JsonBehaviorKind::True,
        'json_behavior_type: FALSE_P' => JsonBehaviorKind::False,
        'json_behavior_type: UNKNOWN' => JsonBehaviorKind::Unknown,
        'json_behavior_type: EMPTY_P ARRAY' => JsonBehaviorKind::EmptyArray,
        'json_behavior_type: EMPTY_P OBJECT_P' => JsonBehaviorKind::EmptyObject,
        'json_behavior_type: EMPTY_P' => JsonBehaviorKind::Empty,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a JSON_QUERY, JSON_EXISTS or JSON_VALUE production of `func_expr_common_subexpr`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function function(Form $form): JsonQueryFunction
    {
        $invocations = $this->lowering->invocations;
        $context = $invocations->jsonValue($form->node(2));
        $path = $this->lowering->expressions->expression($form->node(4));
        $passing = $this->passing($form->node(5));

        return match ($form->signature) {
            'func_expr_common_subexpr: JSON_QUERY ( json_value_expr , a_expr json_passing_clause_opt json_returning_clause_opt json_wrapper_behavior json_quotes_clause_opt json_behavior_clause_opt )' => new JsonQueryFunction(
                JsonFunctionKind::Query,
                $context,
                $path,
                $passing,
                $invocations->jsonReturning($form->node(6)),
                $this->wrapper($form->node(7)),
                $this->quotes($form->node(8)),
                $this->behaviors($form->node(9)),
            ),
            'func_expr_common_subexpr: JSON_EXISTS ( json_value_expr , a_expr json_passing_clause_opt json_on_error_clause_opt )' => new JsonQueryFunction(
                JsonFunctionKind::Exists,
                $context,
                $path,
                $passing,
                null,
                null,
                null,
                $this->behaviors($form->node(6)),
            ),
            'func_expr_common_subexpr: JSON_VALUE ( json_value_expr , a_expr json_passing_clause_opt json_returning_clause_opt json_behavior_clause_opt )' => new JsonQueryFunction(
                JsonFunctionKind::Value,
                $context,
                $path,
                $passing,
                $invocations->jsonReturning($form->node(6)),
                null,
                null,
                $this->behaviors($form->node(7)),
            ),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `json_passing_clause_opt`; no clause is an empty list.
     *
     * @return list<JsonArgument>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function passing(Node $passing): array
    {
        $form = $this->lowering->productions->form($passing);
        if ($form->signature === 'json_passing_clause_opt:') {
            return [];
        }
        if ($form->signature !== 'json_passing_clause_opt: PASSING json_arguments') {
            throw ImplementationGap::production($form);
        }
        $arguments = [];
        foreach ($this->lowering->items($form->node(1), 'json_arguments: json_argument', 'json_arguments: json_arguments , json_argument') as $argument) {
            $element = $this->lowering->productions->form($argument);
            if ($element->signature !== 'json_argument: json_value_expr AS ColLabel') {
                throw ImplementationGap::production($element);
            }
            $arguments[] = new JsonArgument($this->lowering->invocations->jsonValue($element->node(0)), $this->lowering->names->name($element->node(2)));
        }

        return $arguments;
    }

    /**
     * Lowers `json_behavior_clause_opt` or `json_on_error_clause_opt`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function behaviors(Node $clause): ?JsonBehaviorClause
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'json_behavior_clause_opt:', 'json_on_error_clause_opt:' => null,
            'json_behavior_clause_opt: json_behavior ON EMPTY_P' => new JsonBehaviorClause($this->behavior($form->node(0)), null),
            'json_behavior_clause_opt: json_behavior ON ERROR_P', 'json_on_error_clause_opt: json_behavior ON ERROR_P' => new JsonBehaviorClause(null, $this->behavior($form->node(0))),
            'json_behavior_clause_opt: json_behavior ON EMPTY_P json_behavior ON ERROR_P' => new JsonBehaviorClause($this->behavior($form->node(0)), $this->behavior($form->node(3))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `json_behavior` with its `json_behavior_type`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function behavior(Node $behavior): JsonBehavior
    {
        $form = $this->lowering->productions->form($behavior);
        if ($form->signature === 'json_behavior: DEFAULT a_expr') {
            return new JsonBehavior(JsonBehaviorKind::Default, $this->lowering->expressions->expression($form->node(1)));
        }
        if ($form->signature !== 'json_behavior: json_behavior_type') {
            throw ImplementationGap::production($form);
        }
        $type = $this->lowering->productions->form($form->node(0));

        return new JsonBehavior(self::BEHAVIORS[$type->signature] ?? throw ImplementationGap::production($type));
    }

    /**
     * Lowers `json_wrapper_behavior`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function wrapper(Node $wrapper): ?JsonWrapping
    {
        $form = $this->lowering->productions->form($wrapper);
        if (!array_key_exists($form->signature, self::WRAPPERS)) {
            throw ImplementationGap::production($form);
        }
        $kind = self::WRAPPERS[$form->signature];

        return $kind === null ? null : new JsonWrapping($kind);
    }

    /**
     * Lowers `json_quotes_clause_opt`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function quotes(Node $quotes): ?JsonQuoting
    {
        $form = $this->lowering->productions->form($quotes);
        if (!array_key_exists($form->signature, self::QUOTES)) {
            throw ImplementationGap::production($form);
        }
        $keep = self::QUOTES[$form->signature];

        return $keep === null ? null : new JsonQuoting($keep);
    }
}
