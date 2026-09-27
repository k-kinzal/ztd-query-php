<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

use function assert;

use Override;
use SqlSemantics\Core\Composition\Composition;
use SqlSemantics\Core\Composition\Operands;
use SqlSemantics\Core\CompositionException;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Model\PostgreSql\Contract\Contracts;
use SqlSemantics\Statement\Model\PostgreSql\Role\AExprForm;
use SqlSemantics\Statement\Model\PostgreSql\Role\CExprForm;
use SqlSemantics\Statement\Model\PostgreSql\Role\ColIdForm;
use SqlSemantics\Statement\Model\PostgreSql\Role\QualifiedNameForm;

/**
 * Composes PostgreSQL values under stable names.
 *
 * A name is written bare only when the release reads it back as the same
 * name: it is quoted when it holds an upper-case letter, which the server
 * would fold, when its characters need it, or when the grammar reads it as
 * a keyword that cannot be a column name. Strings are standard-conforming:
 * a quote is doubled and a backslash is an ordinary character.
 *
 * @visibility public
 * @example Composing a condition
 *     $builder = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->builder();
 *     \SqlSemantics\Statement\Writer::render($builder->or($builder->compare($builder->column('t', 'User'), '<>', $builder->string('x')), $builder->null())) // => "t.\"User\" <> 'x' OR NULL"
 * @example Shadowing a table with a common table expression
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
 *     $builder = $semantics->builder();
 *     $rows = $builder->unionAll($semantics->analyze('SELECT 1 AS id')->command, $semantics->analyze('SELECT 2')->command);
 *     \SqlSemantics\Statement\Writer::render($builder->with([$builder->cte('users', $rows, ['id'])], $semantics->analyze('SELECT id FROM users')->command)) // => 'WITH users ( id ) AS( SELECT 1 AS id UNION ALL SELECT 2 ) SELECT id FROM users'
 */
final class Builder extends Composition
{
    use Expressions;
    use Queries;
    use Casts;

    private const COMPARISONS = ['=' => '=', '<' => '<', '>' => '>', '<=' => 'LESS_EQUALS', '>=' => 'GREATER_EQUALS', '<>' => 'NOT_EQUALS', '!=' => 'NOT_EQUALS'];

    /**
     * A column, table, or alias name in the ColId role.
     */
    public function identifier(string $name): ColIdForm
    {
        $value = $this->name('ColId', $name);
        assert($value instanceof ColIdForm);

        return $value;
    }

    /**
     * A column reference, qualified by any number of names.
     */
    public function column(string ...$parts): AExprForm
    {
        $value = $this->qualified('columnref', array_values($parts), 'A column reference');
        assert($value instanceof AExprForm);

        return $value;
    }

    /**
     * A table reference, qualified by its schema and optionally its database.
     */
    public function table(string ...$parts): QualifiedNameForm
    {
        $value = $this->qualified('qualified_name', array_values($parts), 'A table reference');
        assert($value instanceof QualifiedNameForm);

        return $value;
    }

    /**
     * A standard-conforming string literal.
     *
     * @throws CompositionException When the value holds a NUL byte, which no PostgreSQL string can
     */
    public function string(string $value): AExprForm
    {
        if (str_contains($value, "\0")) {
            throw new CompositionException('A PostgreSQL string literal cannot hold a NUL byte.');
        }

        return $this->constant($this->quotedString($value, false));
    }

    /**
     * An integer literal, negated through the unary minus.
     */
    public function integer(int $value): AExprForm
    {
        return $this->signed($value < 0, $this->constant(ltrim((string) $value, '-')));
    }

    /**
     * A numeric literal holding exactly the double, which a cast to double precision reads back unchanged.
     */
    public function float(float $value): AExprForm
    {
        return $this->signed($this->negative($value), $this->constant($this->decimal($value)));
    }

    /**
     * TRUE or FALSE.
     */
    public function boolean(bool $value): AExprForm
    {
        return $this->constant($value ? 'TRUE' : 'FALSE');
    }

    /**
     * NULL.
     */
    public function null(): AExprForm
    {
        return $this->constant('NULL');
    }

    /**
     * A hexadecimal bit-string literal.
     */
    public function binary(string $bytes): AExprForm
    {
        return $this->constant($this->hex($bytes));
    }

    /**
     * The numbered `$n` marker, or the named placeholder under the named parameter syntax.
     */
    public function parameter(int|string $marker = 1): AExprForm
    {
        $value = $this->form('c_expr', ['PARAM', 'opt_indirection'], [$this->marker($marker, is_int($marker) ? '$' . $marker : ''), $this->form('opt_indirection', [], [])]);
        assert($value instanceof AExprForm);

        return $value;
    }

    /**
     * `AND`, parenthesizing an operand that binds more weakly.
     */
    public function and(Element $left, Element $right): AExprForm
    {
        return $this->infix(['a_expr', 'AND', 'a_expr'], $left, [], $right);
    }

    /**
     * `OR`, parenthesizing an operand that binds more weakly.
     */
    public function or(Element $left, Element $right): AExprForm
    {
        return $this->infix(['a_expr', 'OR', 'a_expr'], $left, [], $right);
    }

    /**
     * `NOT`, whose operand is parenthesized when it binds more weakly, as a conjunction does.
     */
    public function not(Element $operand): AExprForm
    {
        $symbols = ['NOT', 'a_expr'];
        $value = $this->form('a_expr', $symbols, [$this->operand($operand, 'a_expr', $symbols, 1)]);
        assert($value instanceof AExprForm);

        return $value;
    }

    /**
     * A comparison.
     */
    public function compare(Element $left, string $operator, Element $right): AExprForm
    {
        $terminal = self::COMPARISONS[$operator] ?? throw new CompositionException('Unknown comparison operator ' . $operator);

        return $this->infix(['a_expr', $terminal, 'a_expr'], $left, strlen($terminal) > 1 ? [$operator] : [], $right);
    }

    /**
     * An expression in parentheses.
     */
    public function parenthesized(Element $expression): CExprForm
    {
        $value = $this->form('c_expr', ['(', 'a_expr', ')', 'opt_indirection'], [$this->expect($expression, 'a_expr', 'A parenthesized expression'), $this->form('opt_indirection', [], [])]);
        assert($value instanceof CExprForm);

        return $value;
    }

    /**
     * `IS NULL`, or `IS NOT NULL` when negated.
     */
    #[Override]
    public function isNull(Element $operand, bool $negated = false): AExprForm
    {
        $value = parent::isNull($operand, $negated);
        assert($value instanceof AExprForm);

        return $value;
    }

    /**
     * `IN` a list of values, or `NOT IN` when negated.
     */
    #[Override]
    public function in(Element $operand, array $values, bool $negated = false): AExprForm
    {
        $value = parent::in($operand, $values, $negated);
        assert($value instanceof AExprForm);

        return $value;
    }

    /**
     * A searched CASE.
     */
    #[Override]
    public function case(array $whens, ?Element $else = null): AExprForm
    {
        $value = parent::case($whens, $else);
        assert($value instanceof AExprForm);

        return $value;
    }

    /**
     * A call of a function by name.
     */
    #[Override]
    public function call(string $name, array $arguments = []): AExprForm
    {
        $value = parent::call($name, $arguments);
        assert($value instanceof AExprForm);

        return $value;
    }

    /**
     * `CAST` of an operand to a type.
     */
    #[Override]
    public function cast(Element $operand, TypeDescriptor $type): AExprForm
    {
        $value = parent::cast($operand, $type);
        assert($value instanceof AExprForm);

        return $value;
    }

    #[Override]
    protected function tableSymbol(): string
    {
        return 'qualified_name';
    }

    #[Override]
    protected function expressionSymbol(): string
    {
        return 'a_expr';
    }

    #[Override]
    protected function operands(): Operands
    {
        return new Operands(Contracts::BINDING_POWERS, Contracts::BINDING_OPERANDS, Contracts::BINDING_RULES, $this->language->version);
    }

    #[Override]
    protected function modelNamespace(): string
    {
        return 'SqlSemantics\\Statement\\Model\\PostgreSql';
    }

    #[Override]
    protected function quote(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }

    #[Override]
    protected function bare(string $name): ?string
    {
        return preg_match('/^[a-z_][a-z0-9_$]*$/D', $name) === 1 ? $name : null;
    }

    #[Override]
    protected function identifierTerminals(): array
    {
        return ['IDENT'];
    }

    #[Override]
    protected function admitsBareKeyword(): bool
    {
        return true;
    }

    #[Override]
    protected function envelopes(): array
    {
        return [['stmtmulti', ['stmtmulti', ';', 'toplevel_stmt']]];
    }




}
