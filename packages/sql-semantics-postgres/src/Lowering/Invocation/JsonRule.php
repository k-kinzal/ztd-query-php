<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Invocation;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonArrayAggregate;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonArrayConstructor;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonArrayQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonObjectAggregate;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonObjectConstructor;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonParse;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonScalar;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonSerialize;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonEncoding;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonFormat;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonNullHandling;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonPair;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonReturning;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonUniqueKeys;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\KeyValueSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\KeywordCall;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\KeywordFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\WindowSpecification;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the SQL/JSON constructors and aggregates and the clauses they share with the query functions.
 *
 * Rule: PG-JSON-LOWERING-001. Scope: the JSON_OBJECT, JSON_ARRAY, JSON,
 * JSON_SCALAR and JSON_SERIALIZE productions of `func_expr_common_subexpr`,
 * `json_aggregate_func`, `json_value_expr`, `json_value_expr_list`,
 * `json_format_clause`, `json_format_clause_opt`, `json_encoding_clause_opt`
 * (16), `json_returning_clause_opt` (17), `json_output_clause_opt` (16),
 * `json_name_and_value_list`, `json_name_and_value`,
 * `json_object_constructor_null_clause_opt`,
 * `json_array_constructor_null_clause_opt`,
 * `json_array_aggregate_order_by_clause_opt`,
 * `json_key_uniqueness_constraint_opt`. Constructors: the classes of
 * `Statement/Invocation/Json` and `Json/Constructor`, and `KeywordCall` for
 * the legacy JSON_OBJECT with plain arguments. KEYS after WITH or WITHOUT
 * UNIQUE is noise (see `InvocationNoise`). An encoding other than UTF8, UTF16
 * or UTF32 is rejected by the grammar's action with an `AnalysisException`.
 * Termination: lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-JSON-CREATION-TABLE,
 * https://www.postgresql.org/docs/17/functions-aggregate.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class JsonRule
{
    /**
     * The NULL clauses by production signature.
     */
    private const NULLS = [
        'json_object_constructor_null_clause_opt:' => null,
        'json_object_constructor_null_clause_opt: NULL_P ON NULL_P' => JsonNullHandling::Keep,
        'json_object_constructor_null_clause_opt: ABSENT ON NULL_P' => JsonNullHandling::Absent,
        'json_array_constructor_null_clause_opt:' => null,
        'json_array_constructor_null_clause_opt: NULL_P ON NULL_P' => JsonNullHandling::Keep,
        'json_array_constructor_null_clause_opt: ABSENT ON NULL_P' => JsonNullHandling::Absent,
    ];

    /**
     * The uniqueness clauses by production signature.
     */
    private const UNIQUENESS = [
        'json_key_uniqueness_constraint_opt:' => null,
        'json_key_uniqueness_constraint_opt: WITH UNIQUE KEYS' => true,
        'json_key_uniqueness_constraint_opt: WITH UNIQUE' => true,
        'json_key_uniqueness_constraint_opt: WITHOUT UNIQUE KEYS' => false,
        'json_key_uniqueness_constraint_opt: WITHOUT UNIQUE' => false,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a JSON constructor production of `func_expr_common_subexpr`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function creation(Form $form): Scalar
    {
        $invocations = $this->lowering->invocations;

        return match ($form->signature) {
            'func_expr_common_subexpr: JSON_OBJECT ( func_arg_list )' => new KeywordCall(KeywordFunction::JsonObject, $invocations->arguments($form->node(2))),
            'func_expr_common_subexpr: JSON_OBJECT ( json_name_and_value_list json_object_constructor_null_clause_opt json_key_uniqueness_constraint_opt json_returning_clause_opt )',
            'func_expr_common_subexpr: JSON_OBJECT ( json_name_and_value_list json_object_constructor_null_clause_opt json_key_uniqueness_constraint_opt json_output_clause_opt )' => new JsonObjectConstructor(
                $this->pairs($form->node(2)),
                $this->nulls($form->node(3)),
                $this->uniqueness($form->node(4)),
                $this->returning($form->node(5)),
            ),
            'func_expr_common_subexpr: JSON_OBJECT ( json_returning_clause_opt )', 'func_expr_common_subexpr: JSON_OBJECT ( json_output_clause_opt )' => new JsonObjectConstructor([], null, null, $this->returning($form->node(2))),
            'func_expr_common_subexpr: JSON_ARRAY ( json_value_expr_list json_array_constructor_null_clause_opt json_returning_clause_opt )',
            'func_expr_common_subexpr: JSON_ARRAY ( json_value_expr_list json_array_constructor_null_clause_opt json_output_clause_opt )' => new JsonArrayConstructor(
                $this->values($form->node(2)),
                $this->nulls($form->node(3)),
                $this->returning($form->node(4)),
            ),
            'func_expr_common_subexpr: JSON_ARRAY ( select_no_parens json_format_clause_opt json_returning_clause_opt )',
            'func_expr_common_subexpr: JSON_ARRAY ( select_no_parens json_format_clause_opt json_output_clause_opt )' => new JsonArrayQuery(
                $this->lowering->queries->query($form->node(2)),
                $this->format($form->node(3)),
                $this->returning($form->node(4)),
            ),
            'func_expr_common_subexpr: JSON_ARRAY ( json_returning_clause_opt )', 'func_expr_common_subexpr: JSON_ARRAY ( json_output_clause_opt )' => new JsonArrayConstructor([], null, $this->returning($form->node(2))),
            'func_expr_common_subexpr: JSON ( json_value_expr json_key_uniqueness_constraint_opt )' => new JsonParse($this->value($form->node(2)), $this->uniqueness($form->node(3))),
            'func_expr_common_subexpr: JSON_SCALAR ( a_expr )' => new JsonScalar($this->lowering->expressions->expression($form->node(2))),
            'func_expr_common_subexpr: JSON_SERIALIZE ( json_value_expr json_returning_clause_opt )' => new JsonSerialize($this->value($form->node(2)), $this->returning($form->node(3))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `json_aggregate_func` with the FILTER and OVER that follow it.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function aggregate(Node $aggregate, ?Scalar $filter, WindowSpecification|Name|null $over): Scalar
    {
        $form = $this->lowering->productions->form($aggregate);

        return match ($form->signature) {
            'json_aggregate_func: JSON_OBJECTAGG ( json_name_and_value json_object_constructor_null_clause_opt json_key_uniqueness_constraint_opt json_returning_clause_opt )',
            'json_aggregate_func: JSON_OBJECTAGG ( json_name_and_value json_object_constructor_null_clause_opt json_key_uniqueness_constraint_opt json_output_clause_opt )' => new JsonObjectAggregate(
                $this->keyValue($form->node(2)),
                $this->nulls($form->node(3)),
                $this->uniqueness($form->node(4)),
                $this->returning($form->node(5)),
                $filter,
                $over,
            ),
            'json_aggregate_func: JSON_ARRAYAGG ( json_value_expr json_array_aggregate_order_by_clause_opt json_array_constructor_null_clause_opt json_returning_clause_opt )',
            'json_aggregate_func: JSON_ARRAYAGG ( json_value_expr json_array_aggregate_order_by_clause_opt json_array_constructor_null_clause_opt json_output_clause_opt )' => new JsonArrayAggregate(
                $this->value($form->node(2)),
                $this->order($form->node(3)),
                $this->nulls($form->node(4)),
                $this->returning($form->node(5)),
                $filter,
                $over,
            ),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `json_array_aggregate_order_by_clause_opt`.
     *
     * @return list<\SqlSemantics\Platform\PostgreSql\Statement\Query\SortItem>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function order(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'json_array_aggregate_order_by_clause_opt:' => [],
            'json_array_aggregate_order_by_clause_opt: ORDER BY sortby_list' => $this->lowering->queries->sortClause($form->node(2)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `json_value_expr`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function value(Node $value): JsonValueExpression
    {
        $form = $this->lowering->productions->form($value);
        if ($form->signature !== 'json_value_expr: a_expr json_format_clause_opt') {
            throw ImplementationGap::production($form);
        }

        return new JsonValueExpression($this->lowering->expressions->expression($form->node(0)), $this->format($form->node(1)));
    }

    /**
     * Lowers `json_value_expr_list`.
     *
     * @return list<JsonValueExpression>
     */
    public function values(Node $list): array
    {
        $values = [];
        foreach ($this->lowering->items($list, 'json_value_expr_list: json_value_expr', 'json_value_expr_list: json_value_expr_list , json_value_expr') as $value) {
            $values[] = $this->value($value);
        }

        return $values;
    }

    /**
     * Lowers `json_name_and_value_list`.
     *
     * @return list<JsonPair>
     */
    public function pairs(Node $list): array
    {
        $pairs = [];
        foreach ($this->lowering->items($list, 'json_name_and_value_list: json_name_and_value', 'json_name_and_value_list: json_name_and_value_list , json_name_and_value') as $pair) {
            $pairs[] = $this->keyValue($pair);
        }

        return $pairs;
    }

    /**
     * Lowers `json_name_and_value`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function keyValue(Node $pair): JsonPair
    {
        $form = $this->lowering->productions->form($pair);
        $spelling = match ($form->signature) {
            'json_name_and_value: c_expr VALUE_P json_value_expr' => KeyValueSpelling::Value,
            'json_name_and_value: a_expr : json_value_expr' => KeyValueSpelling::Colon,
            default => throw ImplementationGap::production($form),
        };

        return new JsonPair($this->lowering->expressions->expression($form->node(0)), $this->value($form->node(2)), $spelling);
    }

    /**
     * Lowers `json_format_clause` or `json_format_clause_opt`; no clause is null.
     *
     * @throws AnalysisException When the encoding is not UTF8, UTF16 or UTF32
     * @throws ImplementationGap When the production has no rule
     */
    public function format(Node $format): ?JsonFormat
    {
        $form = $this->lowering->productions->form($format);

        return match ($form->signature) {
            'json_format_clause_opt:' => null,
            'json_format_clause_opt: json_format_clause' => $this->format($form->node(0)),
            'json_format_clause: FORMAT_LA JSON' => new JsonFormat(),
            'json_format_clause: FORMAT_LA JSON ENCODING name' => new JsonFormat($this->encoding($form->node(3))),
            'json_format_clause_opt: FORMAT_LA JSON json_encoding_clause_opt' => new JsonFormat($this->encodingClause($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `json_encoding_clause_opt` of PostgreSQL 16; no clause is null.
     *
     * @throws AnalysisException When the encoding is not UTF8, UTF16 or UTF32
     * @throws ImplementationGap When the production has no rule
     */
    public function encodingClause(Node $clause): ?Name
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'json_encoding_clause_opt:' => null,
            'json_encoding_clause_opt: ENCODING name' => $this->encoding($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the encoding name of a FORMAT clause.
     *
     * @throws AnalysisException When the encoding is not UTF8, UTF16 or UTF32, which the action of `json_format_clause` in `gram.y` rejects (`makeJsonEncoding` called by `json_encoding_clause_opt` in PostgreSQL 16)
     */
    public function encoding(Node $name): Name
    {
        $encoding = $this->lowering->names->name($name);
        if (JsonEncoding::named($encoding->value) === null) {
            throw new AnalysisException('unrecognized JSON encoding: ' . $encoding->value);
        }

        return $encoding;
    }

    /**
     * Lowers `json_returning_clause_opt` or `json_output_clause_opt`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function returning(Node $returning): ?JsonReturning
    {
        $form = $this->lowering->productions->form($returning);

        return match ($form->signature) {
            'json_returning_clause_opt:', 'json_output_clause_opt:' => null,
            'json_returning_clause_opt: RETURNING Typename json_format_clause_opt', 'json_output_clause_opt: RETURNING Typename json_format_clause_opt' => new JsonReturning(
                $this->lowering->types->typeName($form->node(1)),
                $this->format($form->node(2)),
            ),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `json_object_constructor_null_clause_opt` or `json_array_constructor_null_clause_opt`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function nulls(Node $clause): ?JsonNullHandling
    {
        $form = $this->lowering->productions->form($clause);
        if (!array_key_exists($form->signature, self::NULLS)) {
            throw ImplementationGap::production($form);
        }

        return self::NULLS[$form->signature];
    }

    /**
     * Lowers `json_key_uniqueness_constraint_opt`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function uniqueness(Node $constraint): ?JsonUniqueKeys
    {
        $form = $this->lowering->productions->form($constraint);
        if (!array_key_exists($form->signature, self::UNIQUENESS)) {
            throw ImplementationGap::production($form);
        }
        $unique = self::UNIQUENESS[$form->signature];

        return $unique === null ? null : new JsonUniqueKeys($unique);
    }
}
