<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis\Input;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Platform\Sqlite\Analysis\SelectReader;
use SqlSemantics\Platform\Sqlite\IdentifierReader;
use SqlSemantics\Statement\Construction\Expression\ColumnUse;
use SqlSemantics\Statement\Construction\Query as Q;
use SqlSemantics\Statement\Construction\Rendering\ExpressionSql;
use SqlSemantics\Statement\Construction\ScalarInput;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Identifier\Quote;
use SqlSemantics\Statement\Query\Quantifier;

/**
 * Reads complete new query inputs without carrying a bound expression between scopes.
 * @visibility SqlSemantics
 */
final class QueryInputReader
{
    /**
     * Preserves the source query form before any declaration or alias is resolved.
     */
    public function read(Node $source): Q\SelectDefinition|Q\RowsDefinition
    {
        Tree::assertChildren($source, ['selectnowith'], []);
        $body = Tree::child($source, ['selectnowith']);
        assert($body !== null, 'A query has a body.');
        Tree::assertChildren($body, ['oneselect'], []);
        $single = Tree::child($body, ['oneselect']);
        assert($single !== null, 'A simple query has one operation.');
        $rows = Tree::child($single, ['values', 'mvalues']);
        if ($rows !== null) {
            Tree::assertChildren($single, ['values', 'mvalues'], []);
            return $this->rows($rows);
        }
        Tree::assertChildren($single, ['distinct', 'selcollist', 'from', 'where_opt', 'limit_opt'], ['SELECT']);
        $projection = Tree::child($single, ['selcollist']);
        assert($projection !== null, 'A SELECT has an ordered projection.');
        $from = Tree::child($single, ['from']);
        $where = Tree::child($single, ['where_opt']);
        $quantifier = Tree::child($single, ['distinct']);
        $limit = Tree::child($single, ['limit_opt']);
        return new Q\SelectDefinition(
            new Q\ProjectionDefinition(...$this->projection($projection)),
            new Q\Inputs(...($from === null ? [] : $this->tables(Tree::outer($from, ['seltablist'])[0]))),
            $where === null ? null : (new ExpressionInputReader())->read(Tree::outer($where, ['expr'])[0]),
            $quantifier === null ? Quantifier::Default : Quantifier::from(strtoupper(Tree::text($quantifier))),
            $limit === null ? null : $this->limit($limit),
        );
    }

    /**
     * Preserves explicit row order and every operand, including inconsistent widths.
     */
    public function rows(Node $source): Q\RowsDefinition
    {
        $reader = new ExpressionInputReader();
        $rows = [];
        foreach (Tree::outer($source, ['nexprlist']) as $tuple) {
            $values = array_map(static fn (Node $expression): ScalarInput => $reader->read($expression), Tree::outer($tuple, ['expr']));
            assert($values !== [], 'Each tuple contains an expression.');
            $rows[] = new Q\RowDefinition(...$values);
        }
        assert($rows !== [], 'VALUES contains a row.');
        return new Q\RowsDefinition(...$rows);
    }

    /**
     * Retains explicit aliases and the profile's observable expression labels.
     * @return non-empty-list<Q\FieldDefinition>
     */
    public function projection(Node $source): array
    {
        Tree::assertChildren($source, ['sclp', 'scanpt', 'expr', 'as'], []);
        $prefix = Tree::child($source, ['sclp']);
        $fields = $prefix === null ? [] : $this->projection(Tree::outer($prefix, ['selcollist'])[0]);
        $expression = Tree::child($source, ['expr']);
        assert($expression !== null, 'An expression projection has an expression.');
        [$alias, $explicit] = (new SelectReader())->alias(Tree::child($source, ['as']));
        $input = (new ExpressionInputReader())->read($expression);
        $base = $input;
        while ($base instanceof \SqlSemantics\Statement\Construction\Expression\GroupedInput) {
            $base = $base->operand;
        }
        if ($alias === null && !$base instanceof ColumnUse) {
            $label = substr($expression->toString(), strlen($expression->tokens()[0]->leading));
            if ($label !== (new ExpressionSql())->write($input)) {
                $alias = new Name($label, Quote::Double);
            }
        }
        $fields[] = new Q\FieldDefinition($input, $alias, $explicit);
        return $fields;
    }

    /**
     * A repeated name requests a fresh occurrence, including in a self join.
     * @return list<Q\NamedInput>
     */
    public function tables(Node $source): array
    {
        Tree::assertChildren($source, ['stl_prefix', 'nm', 'dbnm', 'as'], []);
        $tables = [];
        $prefix = Tree::child($source, ['stl_prefix']);
        if ($prefix !== null) {
            $join = Tree::child($prefix, ['joinop']);
            if ($join === null || Tree::text($join) !== ',') {
                Tree::unsupported($prefix, 'joined query input');
            }
            $tables = $this->tables(Tree::outer($prefix, ['seltablist'])[0]);
        }
        $first = Tree::child($source, ['nm']);
        assert($first !== null, 'A named input has a relation name.');
        $reader = new IdentifierReader();
        $suffix = Tree::child($source, ['dbnm']);
        $name = $suffix === null ? new QualifiedName($reader->name($first)) : new QualifiedName($reader->name(Tree::outer($suffix, ['nm'])[0]), $reader->name($first));
        [$alias, $explicit] = (new SelectReader())->alias(Tree::child($source, ['as']));
        $tables[] = new Q\NamedInput($name, $alias, $explicit);
        return $tables;
    }

    /**
     * Distinguishes count and offset from their lexical ordering.
     */
    public function limit(Node $source): Q\LimitDefinition
    {
        Tree::assertChildren($source, ['expr'], ['LIMIT', 'OFFSET', ',']);
        $operands = Tree::outer($source, ['expr']);
        assert(count($operands) === 1 || count($operands) === 2, 'LIMIT has one or two operands.');
        $comma = count(array_filter($source->children, static fn (Node|Token $child): bool => $child instanceof Token && $child->text === ',')) !== 0;
        $reader = new ExpressionInputReader();
        $first = $reader->read($operands[0]);
        $second = isset($operands[1]) ? $reader->read($operands[1]) : null;
        if ($comma) {
            assert($second !== null, 'Comma LIMIT has both operands.');
            return new Q\LimitDefinition($second, $first, true);
        }
        return new Q\LimitDefinition($first, $second);
    }
}
