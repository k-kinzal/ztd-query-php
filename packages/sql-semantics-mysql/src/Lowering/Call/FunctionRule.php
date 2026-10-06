<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Call;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Call\CallArgument;
use SqlSemantics\Platform\MySql\Statement\Call\CharCall;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Position;
use SqlSemantics\Platform\MySql\Statement\Call\Trim;
use SqlSemantics\Platform\MySql\Statement\Call\TrimSide;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the function calls with a keyword name and a plain argument list, CHAR, TRIM and the calls written as `name(...)`.
 *
 * Rule: MYSQL-CALL-FUNCTION-001. Scope: function_call_keyword,
 * function_call_nonkeyword, function_call_conflict and geometry_function
 * except the special syntaxes of TemporalRule, WeightRule and JsonRule;
 * function_call_generic, opt_udf_expr_list, udf_expr_list, udf_expr. Each
 * keyword production names its function and the positions of its
 * arguments (an expression, or an expression list that continues the
 * arguments). SUBSTRING with FROM and FOR is SUBSTRING with commas (the
 * synonyms of CallNoise). Constructs: KeywordCall, CharCall, Trim,
 * Position, FunctionCall, CallArgument. Terminates: the argument list is flattened in
 * a loop; every other child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/function-resolution.html,
 * https://dev.mysql.com/doc/refman/8.4/en/built-in-function-reference.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class FunctionRule
{
    /**
     * The keyword productions: the function, the positions of its expressions, and the position of an expression list that follows them.
     */
    private const KEYWORDS = [
        'function_call_keyword: DATE_SYM ( expr )' => [KeywordFunction::Date, [2], null],
        'function_call_keyword: DAY_SYM ( expr )' => [KeywordFunction::Day, [2], null],
        'function_call_keyword: HOUR_SYM ( expr )' => [KeywordFunction::Hour, [2], null],
        'function_call_keyword: INSERT ( expr , expr , expr , expr )' => [KeywordFunction::Insert, [2, 4, 6, 8], null],
        'function_call_keyword: INSERT_SYM ( expr , expr , expr , expr )' => [KeywordFunction::Insert, [2, 4, 6, 8], null],
        'function_call_keyword: INTERVAL_SYM ( expr , expr )' => [KeywordFunction::Interval, [2, 4], null],
        'function_call_keyword: INTERVAL_SYM ( expr , expr , expr_list )' => [KeywordFunction::Interval, [2, 4], 6],
        'function_call_keyword: LEFT ( expr , expr )' => [KeywordFunction::Left, [2, 4], null],
        'function_call_keyword: MINUTE_SYM ( expr )' => [KeywordFunction::Minute, [2], null],
        'function_call_keyword: MONTH_SYM ( expr )' => [KeywordFunction::Month, [2], null],
        'function_call_keyword: RIGHT ( expr , expr )' => [KeywordFunction::Right, [2, 4], null],
        'function_call_keyword: SECOND_SYM ( expr )' => [KeywordFunction::Second, [2], null],
        'function_call_keyword: TIME_SYM ( expr )' => [KeywordFunction::Time, [2], null],
        'function_call_keyword: TIMESTAMP ( expr )' => [KeywordFunction::Timestamp, [2], null],
        'function_call_keyword: TIMESTAMP ( expr , expr )' => [KeywordFunction::Timestamp, [2, 4], null],
        'function_call_keyword: TIMESTAMP_SYM ( expr )' => [KeywordFunction::Timestamp, [2], null],
        'function_call_keyword: TIMESTAMP_SYM ( expr , expr )' => [KeywordFunction::Timestamp, [2, 4], null],
        'function_call_keyword: USER ( )' => [KeywordFunction::User, [], null],
        'function_call_keyword: YEAR_SYM ( expr )' => [KeywordFunction::Year, [2], null],
        'function_call_nonkeyword: ADDDATE_SYM ( expr , expr )' => [KeywordFunction::AddDate, [2, 4], null],
        'function_call_nonkeyword: SUBDATE_SYM ( expr , expr )' => [KeywordFunction::SubDate, [2, 4], null],
        'function_call_nonkeyword: SUBSTRING ( expr , expr , expr )' => [KeywordFunction::Substring, [2, 4, 6], null],
        'function_call_nonkeyword: SUBSTRING ( expr , expr )' => [KeywordFunction::Substring, [2, 4], null],
        'function_call_nonkeyword: SUBSTRING ( expr FROM expr FOR_SYM expr )' => [KeywordFunction::Substring, [2, 4, 6], null],
        'function_call_nonkeyword: SUBSTRING ( expr FROM expr )' => [KeywordFunction::Substring, [2, 4], null],
        'function_call_nonkeyword: LOG_SYM ( expr )' => [KeywordFunction::Log, [2], null],
        'function_call_nonkeyword: LOG_SYM ( expr , expr )' => [KeywordFunction::Log, [2, 4], null],
        'function_call_conflict: ASCII_SYM ( expr )' => [KeywordFunction::Ascii, [2], null],
        'function_call_conflict: CHARSET ( expr )' => [KeywordFunction::Charset, [2], null],
        'function_call_conflict: COALESCE ( expr_list )' => [KeywordFunction::Coalesce, [], 2],
        'function_call_conflict: COLLATION_SYM ( expr )' => [KeywordFunction::Collation, [2], null],
        'function_call_conflict: DATABASE ( )' => [KeywordFunction::Database, [], null],
        'function_call_conflict: IF ( expr , expr , expr )' => [KeywordFunction::If, [2, 4, 6], null],
        'function_call_conflict: FORMAT_SYM ( expr , expr )' => [KeywordFunction::Format, [2, 4], null],
        'function_call_conflict: FORMAT_SYM ( expr , expr , expr )' => [KeywordFunction::Format, [2, 4, 6], null],
        'function_call_conflict: MICROSECOND_SYM ( expr )' => [KeywordFunction::Microsecond, [2], null],
        'function_call_conflict: MOD_SYM ( expr , expr )' => [KeywordFunction::Mod, [2, 4], null],
        'function_call_conflict: OLD_PASSWORD ( expr )' => [KeywordFunction::OldPassword, [2], null],
        'function_call_conflict: PASSWORD ( expr )' => [KeywordFunction::Password, [2], null],
        'function_call_conflict: QUARTER_SYM ( expr )' => [KeywordFunction::Quarter, [2], null],
        'function_call_conflict: REPEAT_SYM ( expr , expr )' => [KeywordFunction::Repeat, [2, 4], null],
        'function_call_conflict: REPLACE ( expr , expr , expr )' => [KeywordFunction::Replace, [2, 4, 6], null],
        'function_call_conflict: REPLACE_SYM ( expr , expr , expr )' => [KeywordFunction::Replace, [2, 4, 6], null],
        'function_call_conflict: REVERSE_SYM ( expr )' => [KeywordFunction::Reverse, [2], null],
        'function_call_conflict: ROW_COUNT_SYM ( )' => [KeywordFunction::RowCount, [], null],
        'function_call_conflict: TRUNCATE_SYM ( expr , expr )' => [KeywordFunction::Truncate, [2, 4], null],
        'function_call_conflict: WEEK_SYM ( expr )' => [KeywordFunction::Week, [2], null],
        'function_call_conflict: WEEK_SYM ( expr , expr )' => [KeywordFunction::Week, [2, 4], null],
        'geometry_function: CONTAINS_SYM ( expr , expr )' => [KeywordFunction::Contains, [2, 4], null],
        'geometry_function: GEOMETRYCOLLECTION ( expr_list )' => [KeywordFunction::GeometryCollection, [], 2],
        'geometry_function: GEOMETRYCOLLECTION ( opt_expr_list )' => [KeywordFunction::GeometryCollection, [], 2],
        'geometry_function: GEOMETRYCOLLECTION_SYM ( opt_expr_list )' => [KeywordFunction::GeometryCollection, [], 2],
        'geometry_function: LINESTRING ( expr_list )' => [KeywordFunction::LineString, [], 2],
        'geometry_function: LINESTRING_SYM ( expr_list )' => [KeywordFunction::LineString, [], 2],
        'geometry_function: MULTILINESTRING ( expr_list )' => [KeywordFunction::MultiLineString, [], 2],
        'geometry_function: MULTILINESTRING_SYM ( expr_list )' => [KeywordFunction::MultiLineString, [], 2],
        'geometry_function: MULTIPOINT ( expr_list )' => [KeywordFunction::MultiPoint, [], 2],
        'geometry_function: MULTIPOINT_SYM ( expr_list )' => [KeywordFunction::MultiPoint, [], 2],
        'geometry_function: MULTIPOLYGON ( expr_list )' => [KeywordFunction::MultiPolygon, [], 2],
        'geometry_function: MULTIPOLYGON_SYM ( expr_list )' => [KeywordFunction::MultiPolygon, [], 2],
        'geometry_function: POINT_SYM ( expr , expr )' => [KeywordFunction::Point, [2, 4], null],
        'geometry_function: POLYGON ( expr_list )' => [KeywordFunction::Polygon, [], 2],
        'geometry_function: POLYGON_SYM ( expr_list )' => [KeywordFunction::Polygon, [], 2],
        'grouping_operation: GROUPING_SYM ( expr_list )' => [KeywordFunction::Grouping, [], 2],
    ];

    /**
     * The TRIM productions: the side, the position of the removed string, and the position of the trimmed string.
     */
    private const TRIMS = [
        'function_call_keyword: TRIM ( expr )' => [null, null, 2],
        'function_call_keyword: TRIM ( LEADING expr FROM expr )' => [TrimSide::Leading, 3, 5],
        'function_call_keyword: TRIM ( TRAILING expr FROM expr )' => [TrimSide::Trailing, 3, 5],
        'function_call_keyword: TRIM ( BOTH expr FROM expr )' => [TrimSide::Both, 3, 5],
        'function_call_keyword: TRIM ( LEADING FROM expr )' => [TrimSide::Leading, null, 4],
        'function_call_keyword: TRIM ( TRAILING FROM expr )' => [TrimSide::Trailing, null, 4],
        'function_call_keyword: TRIM ( BOTH FROM expr )' => [TrimSide::Both, null, 4],
        'function_call_keyword: TRIM ( expr FROM expr )' => [null, 2, 4],
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Tells whether a production is one of this rule.
     */
    public function claims(string $signature): bool
    {
        return isset(self::KEYWORDS[$signature]) || isset(self::TRIMS[$signature]) || in_array($signature, [
            'function_call_keyword: CHAR_SYM ( expr_list )', 'function_call_keyword: CHAR_SYM ( expr_list USING charset_name )',
            'function_call_keyword: CURRENT_USER optional_braces', 'function_call_generic: IDENT_sys ( opt_udf_expr_list )',
            'function_call_generic: ident . ident ( opt_expr_list )', 'function_call_nonkeyword: POSITION_SYM ( bit_expr IN_SYM expr )',
        ], true);
    }

    /**
     * Lowers a production this rule claims.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function call(Form $form): Scalar
    {
        if (isset(self::KEYWORDS[$form->signature])) {
            return $this->keyword($form, ...self::KEYWORDS[$form->signature]);
        }
        if (isset(self::TRIMS[$form->signature])) {
            [$side, $removed, $subject] = self::TRIMS[$form->signature];

            return new Trim($this->expression($form, $subject), $side, $removed === null ? null : $this->expression($form, $removed));
        }
        $expressions = $this->lowering->expressions;

        return match ($form->signature) {
            'function_call_keyword: CHAR_SYM ( expr_list )' => new CharCall($expressions->expressions($form->node(2))),
            'function_call_keyword: CHAR_SYM ( expr_list USING charset_name )' => new CharCall($expressions->expressions($form->node(2)), $this->lowering->charsets->charset($form->node(4))),
            'function_call_keyword: CURRENT_USER optional_braces' => $this->niladic($form, KeywordFunction::CurrentUser),
            'function_call_nonkeyword: POSITION_SYM ( bit_expr IN_SYM expr )' => new Position($expressions->bitExpression($form->node(2)), $this->expression($form, 4)),
            'function_call_generic: IDENT_sys ( opt_udf_expr_list )' => new FunctionCall($this->lowering->names->identifier($form->node(0)), $this->arguments($form->node(2))),
            'function_call_generic: ident . ident ( opt_expr_list )' => new FunctionCall(
                $this->lowering->names->identifier($form->node(2)),
                array_map(static fn (Scalar $argument): CallArgument => new CallArgument($argument), $expressions->expressions($form->node(4))),
                $this->lowering->names->identifier($form->node(0)),
            ),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a keyword production from its table row.
     *
     * @param list<int> $positions The positions of the expressions
     * @param int|null $list The position of an expression list that follows them
     */
    public function keyword(Form $form, KeywordFunction $function, array $positions, ?int $list): KeywordCall
    {
        $arguments = [];
        foreach ($positions as $position) {
            $arguments[] = $this->expression($form, $position);
        }
        if ($list !== null) {
            array_push($arguments, ...$this->lowering->expressions->expressions($form->node($list)));
        }

        return new KeywordCall($function, $arguments);
    }

    /**
     * Lowers a niladic function written with optional empty parentheses.
     */
    public function niladic(Form $form, KeywordFunction $function): KeywordCall
    {
        return new KeywordCall($function, [], $this->lowering->options->present($form->node(1)) ? OptionalWords::Written : OptionalWords::Omitted);
    }

    /**
     * Lowers the expression at a position.
     */
    public function expression(Form $form, int $position): Scalar
    {
        return $this->lowering->expressions->expression($form->node($position));
    }

    /**
     * Lowers the arguments of an unqualified call: a node of opt_udf_expr_list.
     *
     * @return list<CallArgument>
     * @throws ImplementationGap When a production has no rule
     */
    public function arguments(Node $list): array
    {
        $form = $this->lowering->form($list);
        if ($form->signature === 'opt_udf_expr_list:') {
            return [];
        }
        if ($form->signature !== 'opt_udf_expr_list: udf_expr_list') {
            throw ImplementationGap::production($form);
        }
        $spine = $form->node(0);
        $arguments = [];
        foreach ((new Lists())->items($spine) as $item) {
            $arguments[] = $this->argument($item);
        }
        $this->lowering->names->claimed($this->lowering->form($spine), ['udf_expr_list: udf_expr', 'udf_expr_list: udf_expr_list , udf_expr']);

        return $arguments;
    }

    /**
     * Lowers one argument of an unqualified call: a node of udf_expr.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function argument(Node $argument): CallArgument
    {
        $form = $this->lowering->form($argument);
        if ($form->signature === 'udf_expr: remember_name expr remember_end select_alias') {
            $this->lowering->options->skip($form->node(0));
            $this->lowering->options->skip($form->node(2));

            return new CallArgument($this->expression($form, 1), $this->lowering->queries->alias($form->node(3)), $this->lowering->queries->mark($form->node(3)));
        }
        if ($form->signature !== 'udf_expr: expr select_alias') {
            throw ImplementationGap::production($form);
        }

        return new CallArgument($this->expression($form, 0), $this->lowering->queries->alias($form->node(1)), $this->lowering->queries->mark($form->node(1)));
    }
}
