<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Leaf;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Rules\Lexical\Numerals;
use SqlSemantics\Platform\PostgreSql\Rules\Lexical\Strings;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BitStringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BitStringRadix;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NamedParameter;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\PositionalParameter;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\TypedLiteral;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers constants and parameter markers.
 *
 * Rule: PG-LITERAL-001. Scope: `AexprConst`, `Iconst`, `Sconst`,
 * `SignedIconst`, `NumericOnly`, `NumericOnly_list`, `I_or_F_const`,
 * `file_name`, `generic_option_arg`, and the `PARAM`, `ICONST`, `FCONST`,
 * `SCONST`, `BCONST` and `XCONST` terminals. Constructors: `Constant`,
 * `BooleanLiteral`, `NullLiteral`, `TypedLiteral`, `PositionalParameter`,
 * `NamedParameter`, and the value classes they hold. Values are decoded by
 * PG-LEX-STRING-001 and PG-LEX-NUMBER-001. A written plus sign before a
 * number is dropped, as the grammar action drops it. PostgreSQL 17 rejects a
 * parameter number above 2147483647 while scanning.
 * Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-CONSTANTS,
 * https://www.postgresql.org/docs/17/sql-expressions.html#SQL-EXPRESSIONS-PARAMETERS-POSITIONAL.
 * Termination: lists are flattened iteratively. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Literals
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a string constant token.
     */
    public function stringToken(Token $string): StringConstant
    {
        return $this->lowering->leaves->record(new StringConstant((new Strings())->decode($string->text)));
    }

    /**
     * Lowers `Sconst`, `file_name` or `generic_option_arg`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function string(Node $string): StringConstant
    {
        $form = $this->lowering->productions->form($string);

        return match ($form->signature) {
            'Sconst: SCONST' => $this->stringToken($form->token(0)),
            'file_name: Sconst', 'generic_option_arg: Sconst' => $this->string($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a numeric constant token: an integer of any size, or a constant with a point or an exponent.
     */
    public function numberToken(Token $number): IntegerConstant|NumericConstant
    {
        $numerals = new Numerals();
        if ($numerals->integral($number->text)) {
            return $this->lowering->leaves->record(new IntegerConstant($numerals->decimal($number->text)));
        }

        return $this->lowering->leaves->record(new NumericConstant(...$numerals->parts($number->text)));
    }

    /**
     * Lowers `Iconst`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function integer(Node $integer): IntegerConstant
    {
        $form = $this->lowering->productions->form($integer);
        if ($form->signature !== 'Iconst: ICONST') {
            throw ImplementationGap::production($form);
        }

        return $this->lowering->leaves->record(new IntegerConstant((new Numerals())->decimal($form->token(0)->text)));
    }

    /**
     * Lowers `I_or_F_const`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function number(Node $number): IntegerConstant|NumericConstant
    {
        $form = $this->lowering->productions->form($number);

        return match ($form->signature) {
            'I_or_F_const: Iconst' => $this->integer($form->node(0)),
            'I_or_F_const: FCONST' => $this->numberToken($form->token(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `SignedIconst` or `NumericOnly`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function signed(Node $number): SignedNumber
    {
        $form = $this->lowering->productions->form($number);

        return match ($form->signature) {
            'NumericOnly: SignedIconst' => $this->signed($form->node(0)),
            'NumericOnly: FCONST' => new SignedNumber(false, $this->numberToken($form->token(0))),
            'NumericOnly: + FCONST' => new SignedNumber(false, $this->numberToken($form->token(1))),
            'NumericOnly: - FCONST' => new SignedNumber(true, $this->numberToken($form->token(1))),
            'SignedIconst: Iconst' => new SignedNumber(false, $this->integer($form->node(0))),
            'SignedIconst: + Iconst' => new SignedNumber(false, $this->integer($form->node(1))),
            'SignedIconst: - Iconst' => new SignedNumber(true, $this->integer($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `NumericOnly_list`.
     *
     * @return list<SignedNumber>
     */
    public function signedList(Node $list): array
    {
        $numbers = [];
        foreach ($this->lowering->items($list, 'NumericOnly_list: NumericOnly', 'NumericOnly_list: NumericOnly_list , NumericOnly') as $number) {
            $numbers[] = $this->signed($number);
        }

        return $numbers;
    }

    /**
     * Lowers a `PARAM` token: `$n`, or `:name` under the named parameter style.
     *
     * @throws AnalysisException When the number is above 2147483647 in PostgreSQL 17, which the `param` rule of its scanner `scan.l` rejects
     */
    public function parameter(Token $parameter): Scalar
    {
        if (str_starts_with($parameter->text, ':')) {
            return $this->lowering->leaves->record(new NamedParameter(substr($parameter->text, 1)));
        }
        $numerals = new Numerals();
        $number = $numerals->canonical(substr($parameter->text, 1));
        if ($this->lowering->release !== GrammarRelease::PostgreSql166 && !$numerals->within($number, '2147483647')) {
            throw new AnalysisException('parameter number too large');
        }

        return $this->lowering->leaves->record(new PositionalParameter($number));
    }

    /**
     * Lowers `AexprConst`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function constant(Node $constant): Scalar
    {
        $form = $this->lowering->productions->form($constant);
        $strings = new Strings();

        return match ($form->signature) {
            'AexprConst: Iconst' => new Constant($this->integer($form->node(0))),
            'AexprConst: FCONST' => new Constant($this->numberToken($form->token(0))),
            'AexprConst: Sconst' => new Constant($this->string($form->node(0))),
            'AexprConst: BCONST' => new Constant($this->lowering->leaves->record(new BitStringConstant(BitStringRadix::Binary, $strings->digits($form->token(0)->text)))),
            'AexprConst: XCONST' => new Constant($this->lowering->leaves->record(new BitStringConstant(BitStringRadix::Hexadecimal, $strings->digits($form->token(0)->text)))),
            'AexprConst: TRUE_P' => new BooleanLiteral(true),
            'AexprConst: FALSE_P' => new BooleanLiteral(false),
            'AexprConst: NULL_P' => new NullLiteral(),
            'AexprConst: func_name Sconst', 'AexprConst: func_name ( func_arg_list opt_sort_clause ) Sconst', 'AexprConst: ConstTypename Sconst',
            'AexprConst: ConstInterval Sconst opt_interval', 'AexprConst: ConstInterval ( Iconst ) Sconst' => new TypedLiteral(
                $this->lowering->types->constantType($form),
                $this->string($form->node(count($form->node->children) - ($form->signature === 'AexprConst: ConstInterval Sconst opt_interval' ? 2 : 1))),
            ),
            default => throw ImplementationGap::production($form),
        };
    }
}
