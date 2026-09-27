<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

use function assert;

use Override;
use SqlSemantics\Core\Composition\Composition;
use SqlSemantics\Core\Composition\Operands;
use SqlSemantics\Core\CompositionException;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Model\MySql\Contract\Contracts;
use SqlSemantics\Statement\Model\MySql\Role\ExprForm;
use SqlSemantics\Statement\Model\MySql\Role\IdentForm;
use SqlSemantics\Statement\Model\MySql\Role\SimpleExprForm;
use SqlSemantics\Statement\Model\MySql\Role\TableIdentForm;

/**
 * Composes MySQL values under stable names, for every shipped release and the session's `sql_mode`.
 *
 * Names are quoted with backticks when the release reads them as keywords
 * that cannot be identifiers or when their characters need it; after a `.`,
 * the lexer reads any word as a name, so a qualifier's parts are spelled
 * bare more often. Strings are escaped as the mode reads them: backslashes
 * are doubled unless the mode has `NO_BACKSLASH_ESCAPES`. Under
 * `HIGH_NOT_PRECEDENCE`, a negation binds as tightly as `!`, so its operand
 * is parenthesized as the server needs. Common table expressions need
 * release 8.0 or later.
 *
 * @visibility public
 * @example Composing a condition
 *     $builder = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->builder();
 *     \SqlSemantics\Statement\Writer::render($builder->and($builder->compare($builder->column('select'), '=', $builder->string("a'b")), $builder->boolean(true))) // => "`select` = 'a''b' AND TRUE"
 * @example Shadowing a table with a common table expression
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
 *     $builder = $semantics->builder();
 *     $rows = $builder->unionAll($semantics->analyze('SELECT 1 AS id')->command, $semantics->analyze('SELECT 2')->command);
 *     \SqlSemantics\Statement\Writer::render($builder->with([$builder->cte('users', $rows)], $semantics->analyze('SELECT id FROM users')->command)) // => 'WITH users AS( SELECT 1 AS id UNION ALL SELECT 2 ) SELECT id FROM users'
 */
final class Builder extends Composition
{
    use Expressions;
    use Queries;
    use LegacyUnions;

    private const COMPARISONS = ['=' => 'EQ', '<=>' => 'EQUAL_SYM', '>=' => 'GE', '>' => 'GT_SYM', '<=' => 'LE', '<' => 'LT', '<>' => 'NE', '!=' => 'NE'];

    /**
     * A column, table, or alias name.
     */
    public function identifier(string $name): IdentForm
    {
        $value = $this->name('ident', $name);
        assert($value instanceof IdentForm);

        return $value;
    }

    /**
     * A column reference, qualified by up to two names.
     */
    public function column(string ...$parts): ExprForm
    {
        $names = $this->qualified(array_values($parts));
        $value = match (count($names)) {
            1 => $names[0],
            2 => $this->form('simple_ident_q', ['ident', '.', 'ident'], $names),
            3 => $this->form('simple_ident_q', ['ident', '.', 'ident', '.', 'ident'], $names),
            default => throw new CompositionException('A column reference has one to three parts.'),
        };
        assert($value instanceof ExprForm);

        return $value;
    }

    /**
     * A table reference, optionally qualified by its schema.
     */
    public function table(string ...$parts): TableIdentForm
    {
        $names = $this->qualified(array_values($parts));
        $value = match (count($names)) {
            1 => $names[0],
            2 => $this->form('table_ident', ['ident', '.', 'ident'], $names),
            default => throw new CompositionException('A table reference has one or two parts.'),
        };
        assert($value instanceof TableIdentForm);

        return $value;
    }

    /**
     * A string literal, escaped as the mode reads it.
     */
    public function string(string $value): ExprForm
    {
        return $this->literal($this->quotedString($value, !$this->mode()->noBackslashEscapes));
    }

    /**
     * An integer literal, negated through the unary minus of the release.
     */
    public function integer(int $value): ExprForm
    {
        return $this->signed($value < 0, $this->literal(ltrim((string) $value, '-')));
    }

    /**
     * A decimal or floating-point literal.
     */
    public function float(float $value): ExprForm
    {
        return $this->signed($value < 0, $this->literal($this->decimal($value)));
    }

    /**
     * TRUE or FALSE.
     */
    public function boolean(bool $value): ExprForm
    {
        return $this->literal($value ? 'TRUE' : 'FALSE');
    }

    /**
     * NULL.
     */
    public function null(): ExprForm
    {
        return $this->literal('NULL');
    }

    /**
     * A hexadecimal string literal.
     */
    public function binary(string $bytes): ExprForm
    {
        return $this->literal($this->hex($bytes));
    }

    /**
     * The `?` marker, which MySQL does not number, or the named placeholder under the named parameter syntax.
     */
    public function parameter(int|string $marker = 1): ExprForm
    {
        return $this->literal($this->marker($marker, '?'));
    }

    /**
     * `AND`, parenthesizing an operand that binds more weakly.
     */
    public function and(Element $left, Element $right): ExprForm
    {
        return $this->infix('expr', ['expr', 'and', 'expr'], $left, $this->form('and', ['AND_SYM'], []), $right);
    }

    /**
     * `OR`, written as the word whatever the mode makes of `||`.
     */
    public function or(Element $left, Element $right): ExprForm
    {
        return $this->infix('expr', ['expr', 'or', 'expr'], $left, $this->form('or', ['OR_SYM'], []), $right);
    }

    /**
     * `NOT`, whose operand is parenthesized as the mode's precedence of NOT needs.
     */
    public function not(Element $operand): ExprForm
    {
        if ($this->mode()->highNotPrecedence) {
            $symbols = ['not2', 'simple_expr'];
            $value = $this->form('simple_expr', $symbols, [$this->form('not2', ['NOT2_SYM'], ['NOT']), $this->operand($operand, 'simple_expr', $symbols, 1)]);
        } else {
            $value = $this->form('expr', ['NOT_SYM', 'expr'], [$this->operand($operand, 'expr', ['NOT_SYM', 'expr'], 1)]);
        }
        assert($value instanceof ExprForm);

        return $value;
    }

    /**
     * A comparison; `<=>` is the NULL-safe equality of MySQL.
     */
    public function compare(Element $left, string $operator, Element $right): ExprForm
    {
        $terminal = self::COMPARISONS[$operator] ?? throw new CompositionException('Unknown comparison operator ' . $operator);
        if (!$this->has('comp_op', [$terminal])) {
            $symbols = ['bool_pri', $terminal, 'predicate'];
            $value = $this->form('bool_pri', $symbols, [$this->operand($left, 'bool_pri', $symbols, 0), $this->operand($right, 'bool_pri', $symbols, 2)]);
            assert($value instanceof ExprForm);

            return $value;
        }
        $comparison = $this->form('comp_op', [$terminal], $terminal === 'NE' ? [$operator] : []);

        return $this->infix('bool_pri', ['bool_pri', 'comp_op', 'predicate'], $left, $comparison, $right);
    }

    /**
     * An expression in parentheses.
     */
    public function parenthesized(Element $expression): SimpleExprForm
    {
        $value = $this->form('simple_expr', ['(', 'expr', ')'], [$this->expect($expression, 'expr', 'A parenthesized expression')]);
        assert($value instanceof SimpleExprForm);

        return $value;
    }

    #[Override]
    protected function operands(): Operands
    {
        return new Operands(Contracts::BINDING_POWERS, Contracts::BINDING_OPERANDS, Contracts::BINDING_RULES, $this->language->version);
    }

    #[Override]
    protected function modelNamespace(): string
    {
        return 'SqlSemantics\\Statement\\Model\\MySql';
    }

    #[Override]
    protected function quote(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    #[Override]
    protected function bare(string $name): ?string
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_$]*$/D', $name) === 1 ? $name : null;
    }

    #[Override]
    protected function identifierTerminals(): array
    {
        return ['IDENT', 'IDENT_QUOTED'];
    }

    #[Override]
    protected function admitsBareKeyword(): bool
    {
        return true;
    }

    #[Override]
    protected function envelopes(): array
    {
        return [
            ['sql_statement', ['simple_statement_or_begin', ';', 'opt_end_of_input']],
            ['sql_statement', ['simple_statement_or_begin', 'END_OF_INPUT']],
            ['query', ['verb_clause', ';', 'opt_end_of_input']],
            ['query', ['verb_clause', 'END_OF_INPUT']],
        ];
    }






}
