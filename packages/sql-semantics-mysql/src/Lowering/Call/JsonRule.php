<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Call;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponse;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponseKind;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall;
use SqlSemantics\Platform\MySql\Statement\Call\Json\NestedColumns;
use SqlSemantics\Platform\MySql\Statement\Call\Json\OrdinalityColumn;
use SqlSemantics\Platform\MySql\Statement\Call\Json\PathColumn;
use SqlSemantics\Platform\MySql\Statement\Call\JsonTableColumn;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;

/**
 * Lowers JSON_VALUE and the COLUMNS clause of JSON_TABLE with their ON EMPTY and ON ERROR responses.
 *
 * Rule: MYSQL-CALL-JSON-001. Scope: the JSON_VALUE alternative of
 * function_call_keyword, opt_returning_type, opt_on_empty_or_error,
 * opt_on_empty_or_error_json_table, on_empty, on_error, json_on_response,
 * columns_clause, columns_list, jt_column, jt_column_type. A JSON_TABLE
 * column may write ON ERROR before ON EMPTY; the order is kept. Constructs:
 * JsonValueCall, JsonResponse, OrdinalityColumn, PathColumn, NestedColumns.
 * Terminates: the column list is flattened in a loop; a nested COLUMNS
 * clause is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-search-functions.html#function_json-value,
 * https://dev.mysql.com/doc/refman/8.4/en/json-table-functions.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class JsonRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers JSON_VALUE.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function value(Form $form): JsonValueCall
    {
        $this->lowering->names->claimed($form, ['function_call_keyword: JSON_VALUE_SYM ( simple_expr , text_literal opt_returning_type opt_on_empty_or_error )']);
        [$onEmpty, $onError] = $this->responses($form->node(6));

        return new JsonValueCall(
            $this->lowering->expressions->simpleExpression($form->node(2)),
            $this->lowering->literals->string($form->node(4)),
            $this->returning($form->node(5)),
            $onEmpty,
            $onError,
        );
    }

    /**
     * Lowers the optional RETURNING type: a node of opt_returning_type.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function returning(Node $clause): ?CastTarget
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'opt_returning_type:' => null,
            'opt_returning_type: RETURNING_SYM cast_type' => $this->lowering->types->castTarget($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the ON EMPTY and ON ERROR responses: a node of opt_on_empty_or_error.
     *
     * @return array{JsonResponse|null, JsonResponse|null}
     * @throws ImplementationGap When a production has no rule
     */
    public function responses(Node $clause): array
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'opt_on_empty_or_error:' => [null, null],
            'opt_on_empty_or_error: on_empty' => [$this->response($form->node(0), 'on_empty: json_on_response ON_SYM EMPTY_SYM'), null],
            'opt_on_empty_or_error: on_error' => [null, $this->response($form->node(0), 'on_error: json_on_response ON_SYM ERROR_SYM')],
            'opt_on_empty_or_error: on_empty on_error' => [
                $this->response($form->node(0), 'on_empty: json_on_response ON_SYM EMPTY_SYM'),
                $this->response($form->node(1), 'on_error: json_on_response ON_SYM ERROR_SYM'),
            ],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers one ON EMPTY or ON ERROR clause.
     *
     * @param string $signature The production the clause must have
     * @throws ImplementationGap When a production has no rule
     */
    public function response(Node $clause, string $signature): JsonResponse
    {
        $form = $this->lowering->form($clause);
        $this->lowering->names->claimed($form, [$signature]);
        $response = $this->lowering->form($form->node(0));

        return match ($response->signature) {
            'json_on_response: ERROR_SYM' => new JsonResponse(JsonResponseKind::Error),
            'json_on_response: NULL_SYM' => new JsonResponse(JsonResponseKind::Null),
            'json_on_response: DEFAULT_SYM signed_literal' => new JsonResponse(JsonResponseKind::Default, $this->lowering->literals->literal($response->node(1))),
            default => throw ImplementationGap::production($response),
        };
    }

    /**
     * Lowers the COLUMNS clause of JSON_TABLE: a node of columns_clause.
     *
     * @return list<JsonTableColumn>
     * @throws ImplementationGap When a production has no rule
     */
    public function columns(Node $clause): array
    {
        $form = $this->lowering->form($clause);
        $this->lowering->names->claimed($form, ['columns_clause: COLUMNS ( columns_list )']);
        $list = $form->node(2);
        $this->lowering->names->claimed($this->lowering->form($list), ['columns_list: jt_column', 'columns_list: columns_list , jt_column']);
        $columns = [];
        foreach ((new Lists())->items($list) as $column) {
            $columns[] = $this->column($column);
        }

        return $columns;
    }

    /**
     * Lowers one column: a node of jt_column.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function column(Node $column): JsonTableColumn
    {
        $form = $this->lowering->form($column);
        $names = $this->lowering->names;

        return match ($form->signature) {
            'jt_column: ident FOR_SYM ORDINALITY_SYM' => new OrdinalityColumn($names->identifier($form->node(0))),
            'jt_column: ident type opt_collate jt_column_type PATH_SYM text_literal opt_on_empty_or_error_json_table' => $this->path($form),
            'jt_column: NESTED_SYM PATH_SYM text_literal columns_clause' => new NestedColumns($this->lowering->literals->string($form->node(2)), $this->columns($form->node(3))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a path or EXISTS column.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function path(Form $form): PathColumn
    {
        $name = $this->lowering->names->identifier($form->node(0));
        $type = $this->lowering->types->type($form->node(1));
        $collation = $this->lowering->charsets->collation($form->node(2));
        $kind = $this->lowering->form($form->node(3));
        $exists = match ($kind->signature) {
            'jt_column_type:' => false,
            'jt_column_type: EXISTS' => true,
            default => throw ImplementationGap::production($kind),
        };
        $path = $this->lowering->literals->string($form->node(5));
        $responses = $this->lowering->form($form->node(6));
        if ($responses->signature === 'opt_on_empty_or_error_json_table: on_error on_empty') {
            $onError = $this->response($responses->node(0), 'on_error: json_on_response ON_SYM ERROR_SYM');
            $onEmpty = $this->response($responses->node(1), 'on_empty: json_on_response ON_SYM EMPTY_SYM');

            return new PathColumn($name, $type, $path, $exists, $collation, $onEmpty, $onError, true);
        }
        $this->lowering->names->claimed($responses, ['opt_on_empty_or_error_json_table: opt_on_empty_or_error']);
        [$onEmpty, $onError] = $this->responses($responses->node(0));

        return new PathColumn($name, $type, $path, $exists, $collation, $onEmpty, $onError);
    }
}
