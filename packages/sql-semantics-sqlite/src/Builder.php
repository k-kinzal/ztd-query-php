<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use function assert;

use Override;
use SqlSemantics\Core\Composition\Composition;
use SqlSemantics\Core\Composition\Operands;
use SqlSemantics\Core\CompositionException;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Model\Sqlite\Contract\Contracts;
use SqlSemantics\Statement\Model\Sqlite\Role\ExprForm;
use SqlSemantics\Statement\Model\Sqlite\Role\FullnameForm;
use SqlSemantics\Statement\Model\Sqlite\Role\NmForm;
use SqlSemantics\Statement\Model\Sqlite\Role\SelectForm;
use SqlSemantics\Statement\Model\Sqlite\Role\SelectnowithForm;
use SqlSemantics\Statement\Model\Sqlite\Role\WqitemForm;

/**
 * Composes SQLite values under stable names.
 *
 * A name that spells a keyword is always quoted, even one the parser would
 * fall back to reading as an identifier, because the fallback depends on the
 * position the name is written in. Double quotes delimit names; strings
 * double their quotes and know no escapes.
 *
 * @visibility public
 * @example Composing a condition
 *     $builder = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->builder();
 *     \SqlSemantics\Statement\Writer::render($builder->not($builder->and($builder->compare($builder->column('key'), '>=', $builder->integer(-1)), $builder->parameter()))) // => 'NOT( "key" >= - 1 AND ?1 )'
 * @example Shadowing a table with a common table expression
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $builder = $semantics->builder();
 *     $rows = $builder->unionAll($semantics->analyze('SELECT 1 AS id')->command, $semantics->analyze('SELECT 2')->command);
 *     \SqlSemantics\Statement\Writer::render($builder->with([$builder->cte('users', $rows)], $semantics->analyze('SELECT id FROM users')->command)) // => 'WITH users AS( SELECT 1 AS id UNION ALL SELECT 2 ) SELECT id FROM users'
 */
final class Builder extends Composition
{
    use Expressions;

    private const COMPARISONS = ['=' => 'EQ|NE', '==' => 'EQ|NE', '<>' => 'EQ|NE', '!=' => 'EQ|NE', '<' => 'LT|GT|GE|LE', '>' => 'LT|GT|GE|LE', '<=' => 'LT|GT|GE|LE', '>=' => 'LT|GT|GE|LE'];

    /**
     * A column, table, or alias name in the nm role.
     */
    public function identifier(string $name): NmForm
    {
        $value = $this->name('nm', $name);
        assert($value instanceof NmForm);

        return $value;
    }

    /**
     * A column reference, qualified by up to two names.
     */
    public function column(string ...$parts): ExprForm
    {
        $parts = array_values($parts);
        $value = match (count($parts)) {
            1 => $this->name('expr', $parts[0]),
            2 => $this->form('expr', ['nm', 'DOT', 'nm'], [$this->identifier($parts[0]), $this->identifier($parts[1])]),
            3 => $this->form('expr', ['nm', 'DOT', 'nm', 'DOT', 'nm'], [$this->identifier($parts[0]), $this->identifier($parts[1]), $this->identifier($parts[2])]),
            default => throw new CompositionException('A column reference has one to three parts.'),
        };
        assert($value instanceof ExprForm);

        return $value;
    }

    /**
     * A table reference, optionally qualified by its schema.
     */
    public function table(string ...$parts): FullnameForm
    {
        $parts = array_values($parts);
        $value = match (count($parts)) {
            1 => $this->identifier($parts[0]),
            2 => $this->form('fullname', ['nm', 'DOT', 'nm'], [$this->identifier($parts[0]), $this->identifier($parts[1])]),
            default => throw new CompositionException('A table reference has one or two parts.'),
        };
        assert($value instanceof FullnameForm);

        return $value;
    }

    /**
     * A string literal.
     *
     * @throws CompositionException When the value holds a NUL byte, which ends SQL text for SQLite
     */
    public function string(string $value): ExprForm
    {
        if (str_contains($value, "\0")) {
            throw new CompositionException('An SQLite string literal cannot hold a NUL byte.');
        }

        return $this->term($this->quotedString($value, false));
    }

    /**
     * An integer literal, negated through the unary minus.
     */
    public function integer(int $value): ExprForm
    {
        return $this->signed($value < 0, $this->term(ltrim((string) $value, '-')));
    }

    /**
     * A floating-point literal holding exactly the double, which SQLite reads as a REAL.
     */
    public function float(float $value): ExprForm
    {
        return $this->signed($this->negative($value), $this->term($this->decimal($value)));
    }

    /**
     * TRUE or FALSE, which SQLite reads as identifiers.
     */
    public function boolean(bool $value): ExprForm
    {
        return $this->term($value ? 'TRUE' : 'FALSE');
    }

    /**
     * NULL.
     */
    public function null(): ExprForm
    {
        return $this->term('NULL');
    }

    /**
     * A BLOB literal.
     */
    public function binary(string $bytes): ExprForm
    {
        return $this->term($this->hex($bytes));
    }

    /**
     * The numbered `?n` marker, or the named placeholder `:name`, which SQLite reads itself.
     */
    public function parameter(int|string $marker = 1): ExprForm
    {
        return $this->term($this->marker($marker, is_int($marker) ? '?' . $marker : ''));
    }

    /**
     * `AND`, parenthesizing an operand that binds more weakly.
     */
    public function and(Element $left, Element $right): ExprForm
    {
        return $this->infix(['expr', 'AND', 'expr'], $left, [], $right);
    }

    /**
     * `OR`, parenthesizing an operand that binds more weakly.
     */
    public function or(Element $left, Element $right): ExprForm
    {
        return $this->infix(['expr', 'OR', 'expr'], $left, [], $right);
    }

    /**
     * `NOT`, whose operand is parenthesized when it binds more weakly.
     */
    public function not(Element $operand): ExprForm
    {
        $symbols = ['NOT', 'expr'];
        $value = $this->form('expr', $symbols, [$this->operand($operand, 'expr', $symbols, 1)]);
        assert($value instanceof ExprForm);

        return $value;
    }

    /**
     * A comparison; `==` is SQLite's spelling of equality too.
     */
    public function compare(Element $left, string $operator, Element $right): ExprForm
    {
        $class = self::COMPARISONS[$operator] ?? throw new CompositionException('Unknown comparison operator ' . $operator);

        return $this->infix(['expr', $class, 'expr'], $left, [$operator], $right);
    }

    /**
     * An expression in parentheses.
     */
    public function parenthesized(Element $expression): ExprForm
    {
        $value = $this->form('expr', ['LP', 'expr', 'RP'], [$this->expect($expression, 'expr', 'A parenthesized expression')]);
        assert($value instanceof ExprForm);

        return $value;
    }

    /**
     * `UNION ALL`; SQLite writes a compound left to right, so the right operand is one SELECT.
     */
    public function unionAll(Element $left, Element $right): SelectnowithForm
    {
        $value = $this->form('selectnowith', ['selectnowith', 'multiselect_op', 'oneselect'], [
            $this->expect($this->unwrap($left), 'selectnowith', 'The left set operand'),
            $this->form('multiselect_op', ['UNION', 'ALL'], []),
            $this->expect($this->unwrap($right), 'oneselect', 'The right set operand'),
        ]);
        assert($value instanceof SelectnowithForm);

        return $value;
    }

    /**
     * A common table expression over a SELECT.
     */
    public function cte(string $name, Element $query, array $columns = []): WqitemForm
    {
        $entries = array_map(fn (string $column): Element => $this->form('eidlist', ['nm', 'collate', 'sortorder'], [$this->identifier($column), $this->form('collate', [], []), $this->form('sortorder', [], [])]), $columns);
        $list = $columns === [] ? $this->form('eidlist_opt', [], []) : $this->form('eidlist_opt', ['LP', 'eidlist', 'RP'], [$this->eidlist($entries)]);
        $value = $this->form('wqitem', ['withnm', 'eidlist_opt', 'wqas', 'LP', 'select', 'RP'], [$this->identifier($name), $list, $this->form('wqas', ['AS'], []), $this->expect($this->unwrap($query), 'select', 'The query of a common table expression')]);
        assert($value instanceof WqitemForm);

        return $value;
    }

    /**
     * A SELECT preceded by a WITH clause; a SELECT that has one keeps its expressions after the new ones.
     */
    public function with(array $ctes, Element $query): SelectForm
    {
        if ($ctes === []) {
            throw new CompositionException('A WITH clause needs at least one common table expression.');
        }
        foreach ($ctes as $cte) {
            $this->expect($cte, 'wqitem', 'A common table expression');
        }
        $query = $this->unwrap($query);
        $listSymbols = ['wqlist', 'COMMA', 'wqitem'];
        $shapes = [['WITH', 'wqlist', 'selectnowith'], ['WITH', 'RECURSIVE', 'wqlist', 'selectnowith']];
        foreach ($shapes as $symbols) {
            $class = $this->classOf('select', $symbols);
            if ($class !== null && $query instanceof $class) {
                [$list, $body] = $query->children();
                $value = $this->form('select', $symbols, [$this->fold('wqlist', $listSymbols, [...$ctes, ...$this->unfold($list, 'wqlist', $listSymbols)]), $body]);
                assert($value instanceof SelectForm);

                return $value;
            }
        }
        $value = $this->form('select', $shapes[0], [$this->fold('wqlist', $listSymbols, $ctes), $this->expect($query, 'selectnowith', 'The query after a WITH clause')]);
        assert($value instanceof SelectForm);

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
        return 'SqlSemantics\\Statement\\Model\\Sqlite';
    }

    #[Override]
    protected function quote(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }

    #[Override]
    protected function bare(string $name): ?string
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_$]*$/D', $name) === 1 ? $name : null;
    }

    #[Override]
    protected function identifierTerminals(): array
    {
        return ['ID'];
    }

    #[Override]
    protected function admitsBareKeyword(): bool
    {
        return false;
    }

    #[Override]
    protected function envelopes(): array
    {
        return [['ecmd', ['cmdx', 'SEMI']]];
    }




}
