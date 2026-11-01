<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dml;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerClose;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexRead;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerOpen;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\IndexDirection;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\KeyComparison;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\OpenHandler;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\ScanDirection;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers HANDLER of every release.
 *
 * Rule: MYSQL-HANDLER-LOWERING-001. Scope: handler, handler_read_or_scan
 * (5.6, 5.7); handler_stmt (8.0 and later); handler_scan_function,
 * handler_rkey_function, handler_rkey_mode. OPEN and CLOSE are HandlerOpen
 * and HandlerClose; a read is a scan (HandlerScan), an index read
 * (HandlerIndexRead) or an index seek (HandlerIndexSeek). Terminates: the
 * parts are strict subtrees. Source: https://dev.mysql.com/doc/refman/8.4/en/handler.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Dml
 */
final class HandlerRule
{
    /**
     * The read productions and the positions of the handler name, the read, WHERE and LIMIT.
     */
    private const READS = [
        'handler: HANDLER_SYM table_ident_nodb READ_SYM handler_read_or_scan where_clause opt_limit_clause' => [3, 4, 5],
        'handler: HANDLER_SYM table_ident_nodb READ_SYM handler_read_or_scan opt_where_clause opt_limit_clause' => [3, 4, 5],
        'handler_stmt: HANDLER_SYM ident READ_SYM handler_scan_function opt_where_clause opt_limit_clause' => [3, 4, 5],
        'handler_stmt: HANDLER_SYM ident READ_SYM ident handler_rkey_function opt_where_clause opt_limit_clause' => [3, 5, 6],
        'handler_stmt: HANDLER_SYM ident READ_SYM ident handler_rkey_mode ( values ) opt_where_clause opt_limit_clause' => [3, 8, 9],
    ];

    /**
     * The scan directions by production.
     */
    private const SCANS = ['handler_scan_function: FIRST_SYM' => ScanDirection::First, 'handler_scan_function: NEXT_SYM' => ScanDirection::Next];

    /**
     * The index directions by production.
     */
    private const DIRECTIONS = [
        'handler_rkey_function: FIRST_SYM' => IndexDirection::First, 'handler_rkey_function: NEXT_SYM' => IndexDirection::Next,
        'handler_rkey_function: PREV_SYM' => IndexDirection::Previous, 'handler_rkey_function: LAST_SYM' => IndexDirection::Last,
    ];

    /**
     * The key comparisons by production.
     */
    private const MODES = [
        'handler_rkey_mode: EQ' => KeyComparison::Equal, 'handler_rkey_mode: GE' => KeyComparison::GreaterOrEqual, 'handler_rkey_mode: LE' => KeyComparison::LessOrEqual,
        'handler_rkey_mode: GT_SYM' => KeyComparison::Greater, 'handler_rkey_mode: LT' => KeyComparison::Less,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a node of `handler` or `handler_stmt`.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When a read retains a non-scalar operand or subquery
     */
    public function statement(Form $form): Statement
    {
        switch ($form->signature) {
            case 'handler: HANDLER_SYM table_ident OPEN_SYM opt_table_alias':
            case 'handler_stmt: HANDLER_SYM table_ident OPEN_SYM opt_table_alias':
                return new HandlerOpen($this->lowering->names->qualified($form->node(1)), $this->lowering->queries->alias($form->node(3)), $this->lowering->queries->mark($form->node(3)));
            case 'handler: HANDLER_SYM table_ident_nodb CLOSE_SYM':
                return new HandlerClose($this->lowering->names->qualified($form->node(1))->name);
            case 'handler_stmt: HANDLER_SYM ident CLOSE_SYM':
                return new HandlerClose($this->lowering->names->identifier($form->node(1)));
            default:
                return $this->read($form);
        }
    }

    /**
     * Lowers a read of a handler.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When a read retains a non-scalar operand or subquery
     */
    public function read(Form $form): Statement
    {
        $rejected = (new SyntaxHandlerRead())->rejected($form->node, $this->lowering->profile->grammar);
        if ($rejected !== null) {
            throw $rejected;
        }
        [$read, $where, $limit] = self::READS[$form->signature] ?? throw ImplementationGap::production($form);
        $end = (new \SqlSemantics\Construction\SourceRanges())->span($form->node)[1] ?? 0;
        $name = $form->node(1)->name === 'table_ident_nodb' ? $this->lowering->names->qualified($form->node(1))->name : $this->lowering->names->identifier($form->node(1));
        $handler = new OpenHandler($name);
        $condition = (new \SqlSemantics\Platform\MySql\Lowering\Query\Shared\StoredClauses($this->lowering))->where($form->node($where));
        $rows = $this->lowering->queries->limit($form->node($limit));
        $kind = $this->lowering->form($form->node($read));
        if ($kind->signature === 'handler_read_or_scan: handler_scan_function') {
            $kind = $this->lowering->form($kind->node(0));
        }
        if (isset(self::SCANS[$kind->signature])) {
            if ($condition instanceof RowOperand || $condition instanceof DeferredOperand || $this->stored($condition === null ? [] : [$condition])) {
                throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Dml\Handler\RefusedRead($handler, self::SCANS[$kind->signature], where: $condition, limit: $rows), (new \SqlSemantics\Construction\SourceRanges())->span($form->node)[1] ?? 0, false);
            }
            return new HandlerScan($handler, self::SCANS[$kind->signature], $condition, $rows);
        }
        if ($kind->signature === 'handler_read_or_scan: ident handler_rkey_function') {
            return $this->index($handler, $kind->node(0), $this->lowering->form($kind->node(1)), $condition, $rows, $end);
        }

        $seek = $form->signature === 'handler_stmt: HANDLER_SYM ident READ_SYM ident handler_rkey_mode ( values ) opt_where_clause opt_limit_clause' ? $form : null;

        return $this->index($handler, $form->node(3), $this->lowering->form($form->node(4)), $condition, $rows, $end, $seek);
    }

    /**
     * Lowers an index read or an index seek.
     *
     * @param Form|null $seek The 8.0 statement that writes the comparison and the values itself
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When a read retains a non-scalar operand or subquery
     */
    public function index(OpenHandler $handler, Node $index, Form $function, Scalar|RowOperand|DeferredOperand|null $where, ?Limit $limit, int $end, ?Form $seek = null): Statement
    {
        $name = $this->lowering->names->identifier($index);
        if ($seek === null && isset(self::DIRECTIONS[$function->signature])) {
            if ($where instanceof RowOperand || $where instanceof DeferredOperand || $this->stored($where === null ? [] : [$where])) {
                throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Dml\Handler\RefusedRead($handler, self::DIRECTIONS[$function->signature], $name, where: $where, limit: $limit), $end, false);
            }
            return new HandlerIndexRead($handler, $name, self::DIRECTIONS[$function->signature], $where, $limit);
        }
        [$mode, $values] = match (true) {
            $seek !== null => [$function, $seek->node(6)],
            $function->signature === 'handler_rkey_function: handler_rkey_mode ( values )' => [$this->lowering->form($function->node(0)), $function->node(2)],
            default => throw ImplementationGap::production($function),
        };
        $comparison = self::MODES[$mode->signature] ?? throw ImplementationGap::production($mode);

        $operands = [];
        foreach ((new ListRule($this->lowering))->items($values, ['values: values , expr_or_default', 'values: expr_or_default']) as $item) {
            $value = $this->lowering->form($item);
            $operands[] = $value->signature === 'expr_or_default: expr' ? $this->lowering->expressions->operand($value->node(0)) : (new ValueRule($this->lowering))->value($item);
        }
        if ($where instanceof RowOperand || $where instanceof DeferredOperand || array_filter($operands, static fn ($value): bool => $value instanceof RowOperand || $value instanceof DeferredOperand) !== [] || $this->stored([...$operands, ...($where === null ? [] : [$where])])) {
            throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Dml\Handler\RefusedRead($handler, $comparison, $name, $operands, $where, $limit), $end, false);
        }
        $ordinary = [];
        foreach ($operands as $operand) {
            \SqlSemantics\Diagnostic\Check::invariant($operand instanceof Scalar || $operand instanceof \SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest, 'An ordinary handler key has scalar or DEFAULT operands.');
            $ordinary[] = $operand;
        }
        return new HandlerIndexSeek($handler, $name, $comparison, $ordinary, $where, $limit);
    }

    /**
     * Identifies stored calls whose complete read must remain a typed refusal.
     *
     * @param list<\SqlSemantics\Statement\Node> $operands The complete key and predicate operands
     */
    public function stored(array $operands): bool
    {
        $releases = (new \SqlSemantics\Platform\MySql\Rules\Dml\Handler\HandlerOperands())->releases($operands);
        $bit = (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain())->bit($this->lowering->profile->grammar);
        return ($releases & $bit) === 0;
    }
}
