<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Leaf;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers operator names.
 *
 * Rule: PG-OPERATOR-NAME-001. Scope: `all_Op`, `MathOp`, `qual_Op`,
 * `qual_all_Op`, `any_operator`, and the operator tokens the expression
 * grammar writes inline. Constructor: `OperatorName`. The name is the
 * operator text; the token `!=` is the operator `<>`, as the scanner converts
 * it. `OPERATOR(schema.op)` keeps its qualifiers and the fact that the
 * syntax was used. Termination: the qualifier chain is walked iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-OPERATORS. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Operators
{
    /**
     * The operator each self-delimiting or fixed operator terminal names.
     */
    private const SYMBOLS = [
        '+' => '+', '-' => '-', '*' => '*', '/' => '/', '%' => '%', '^' => '^', '<' => '<', '>' => '>', '=' => '=',
        'LESS_EQUALS' => '<=', 'GREATER_EQUALS' => '>=', 'NOT_EQUALS' => '<>',
    ];

    /**
     * The productions of `MathOp`.
     */
    private const MATH = [
        'MathOp: +', 'MathOp: -', 'MathOp: *', 'MathOp: /', 'MathOp: %', 'MathOp: ^', 'MathOp: <', 'MathOp: >', 'MathOp: =',
        'MathOp: LESS_EQUALS', 'MathOp: GREATER_EQUALS', 'MathOp: NOT_EQUALS',
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an operator token: `Op`, or one of the arithmetic and comparison terminals.
     *
     * @throws ImplementationGap When the token is not an operator
     */
    public function symbol(Token $operator): Name
    {
        if ($operator->name === 'Op') {
            return $this->lowering->leaves->record(new Name($operator->text));
        }

        return $this->lowering->leaves->record(new Name(self::SYMBOLS[$operator->name] ?? throw ImplementationGap::rule('operator token ' . $operator->name)));
    }

    /**
     * Lowers `all_Op`, `MathOp`, `qual_Op`, `qual_all_Op` or `any_operator`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function operator(Node $operator): OperatorName
    {
        $form = $this->lowering->productions->form($operator);
        if (in_array($form->signature, self::MATH, true)) {
            return new OperatorName($this->symbol($form->token(0)));
        }

        return match ($form->signature) {
            'all_Op: Op', 'qual_Op: Op' => new OperatorName($this->symbol($form->token(0))),
            'all_Op: MathOp', 'qual_all_Op: all_Op' => $this->operator($form->node(0)),
            'qual_Op: OPERATOR ( any_operator )', 'qual_all_Op: OPERATOR ( any_operator )' => $this->qualified($form->node(2), true),
            'any_operator: all_Op', 'any_operator: ColId . any_operator' => $this->qualified($operator, false),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the qualifier chain of `any_operator`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function qualified(Node $operator, bool $explicit): OperatorName
    {
        $qualifiers = [];
        $current = $operator;
        while (true) {
            $form = $this->lowering->productions->form($current);
            if ($form->signature === 'any_operator: all_Op') {
                return new OperatorName($this->operator($form->node(0))->name, $qualifiers, $explicit);
            }
            if ($form->signature !== 'any_operator: ColId . any_operator') {
                throw ImplementationGap::production($form);
            }
            $qualifiers[] = $this->lowering->names->name($form->node(0));
            $current = $form->node(2);
        }
    }
}
