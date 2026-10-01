<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Statement\Expression\BooleanReference;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Expression\SqliteInteger;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Identifier\Quote;
use SqlSemantics\Statement\Literal\Radix;
use SqlSemantics\Statement\Literal\UnsignedInteger;
use SqlSemantics\Statement\Projection\Field;
use SqlSemantics\Statement\Projection\Fields;
use SqlSemantics\Statement\Query\Quantifier;
use SqlSemantics\Statement\Query\Select;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;

/**
 * Lowers a query's inputs and projection into references sharing one semantic scope.
 * @visibility SqlSemantics
 */
final class SelectReader
{
    /**
     * Structures the represented clauses; additional query forms require their own lowering.
     */
    public function read(Node $source, Catalog $catalog): Select
    {
        Tree::assertChildren($source, ['selectnowith'], []);
        $body = Tree::child($source, ['selectnowith']);
        assert($body !== null, 'A query has a query body.');
        Tree::assertChildren($body, ['oneselect'], []);
        $select = Tree::child($body, ['oneselect']);
        assert($select !== null, 'A simple query has a SELECT body.');
        Tree::assertChildren($select, ['distinct', 'selcollist', 'from', 'where_opt'], ['SELECT']);
        $from = Tree::child($select, ['from']);
        $tables = $from === null ? [] : $this->tables(Tree::outer($from, ['seltablist'])[0], $catalog);
        $scope = new Scope($catalog, ...$tables);
        $projection = Tree::child($select, ['selcollist']);
        assert($projection !== null, 'This query grammar requires a projection.');
        $fields = new Fields($scope, ...$this->projection($projection, $scope));
        $predicate = Tree::child($select, ['where_opt']);
        $where = $predicate === null ? null : $this->expression(Tree::outer($predicate, ['expr'])[0], $scope);
        $quantifier = Tree::child($select, ['distinct']);
        return new Select($fields, $where, $quantifier === null ? Quantifier::Default : Quantifier::from(strtoupper(Tree::text($quantifier))));
    }

    /**
     * Creates a distinct relation occurrence for each named input position.
     * @return list<TableReference>
     */
    public function tables(Node $source, Catalog $catalog): array
    {
        Tree::assertChildren($source, ['stl_prefix', 'nm', 'dbnm', 'as'], []);
        $tables = [];
        $prefix = Tree::child($source, ['stl_prefix']);
        if ($prefix !== null) {
            $join = Tree::child($prefix, ['joinop']);
            if ($join === null || Tree::text($join) !== ',') {
                Tree::unsupported($prefix, 'joined query input');
            }
            $tables = $this->tables(Tree::outer($prefix, ['seltablist'])[0], $catalog);
        }
        $first = Tree::child($source, ['nm']);
        assert($first !== null, 'A named input has a relation name.');
        $reader = new IdentifierReader();
        $suffix = Tree::child($source, ['dbnm']);
        $name = $suffix === null
            ? new QualifiedName($reader->name($first))
            : new QualifiedName($reader->name(Tree::outer($suffix, ['nm'])[0]), $reader->name($first));
        [$alias, $explicit] = $this->alias(Tree::child($source, ['as']));
        $tables[] = new TableReference($catalog, $name, $alias, $explicit);
        return $tables;
    }

    /**
     * Retains projection order and gives every expression the actual input scope.
     * @return non-empty-list<Field>
     */
    public function projection(Node $source, Scope $scope): array
    {
        Tree::assertChildren($source, ['sclp', 'scanpt', 'expr', 'as'], []);
        $prefix = Tree::child($source, ['sclp']);
        $fields = $prefix === null ? [] : $this->projection(Tree::outer($prefix, ['selcollist'])[0], $scope);
        $expression = Tree::child($source, ['expr']);
        assert($expression !== null, 'An expression projection has an expression.');
        [$alias, $explicit] = $this->alias(Tree::child($source, ['as']));
        $operand = $this->expression($expression, $scope);
        $derived = $alias === null && ($operand instanceof NullConstant || $operand instanceof SqliteInteger) ? new Name($operand->toString(), Quote::Double) : null;
        $fields[] = new Field($operand, $alias, derivedName: $derived, explicitAlias: $explicit);
        return $fields;
    }

    /**
     * Separates numeric and NULL constants from names with possible truth alternatives.
     */
    public function expression(Node $source, Scope $scope): ScalarExpression
    {
        $term = Tree::child($source, ['term']);
        if ($term !== null && count($term->tokens()) === 1 && $term->tokens()[0]->name === 'NULL') {
            return new NullConstant($term->tokens()[0]->text);
        }
        if ($term !== null && count($term->tokens()) === 1 && in_array($term->tokens()[0]->name, ['INTEGER', 'QNUMBER'], true)) {
            $text = $term->tokens()[0]->text;
            $hexadecimal = str_starts_with(strtolower($text), '0x');
            if ($hexadecimal || ctype_digit(str_replace('_', '', $text))) {
                return new SqliteInteger(new UnsignedInteger($hexadecimal ? substr($text, 2) : $text, $hexadecimal ? Radix::Hexadecimal : Radix::Decimal), uppercasePrefix: str_starts_with($text, '0X'));
            }
        }
        $column = $this->column($source, $scope);
        return $column->qualifier === null && $column->name->quote === Quote::None && in_array(strtoupper($column->name->value), ['TRUE', 'FALSE'], true)
            ? new BooleanReference($column)
            : $column;
    }

    /**
     * Reads a column lookup without treating other expression forms as names.
     */
    public function column(Node $source, Scope $scope): ColumnReference
    {
        $children = Tree::significant($source);
        if (count($children) === 1 && $children[0] instanceof Token && in_array($children[0]->name, ['ID', 'INDEXED', 'JOIN_KW'], true)) {
            $parts = [$children[0]];
        } else {
            Tree::assertChildren($source, ['nm'], ['.']);
            $parts = Tree::outer($source, ['nm']);
        }
        assert(count($parts) >= 1 && count($parts) <= 3, 'A column has up to three name positions.');
        $names = array_map((new IdentifierReader())->name(...), $parts);
        $name = $names[count($names) - 1];
        $qualifier = count($names) === 1 ? null : new QualifiedName($names[count($names) - 2], count($names) === 3 ? $names[0] : null);
        $column = new ColumnReference($scope, $name, $qualifier);
        if ($qualifier === null && !$column->resolution instanceof ResolvedColumn && $name->quote === Quote::Double) {
            Tree::unsupported($source, 'identifier with a literal alternative');
        }
        return $column;
    }

    /**
     * Separates alias meaning from the optional AS spelling.
     * @return array{?Name, bool}
     */
    public function alias(?Node $source): array
    {
        if ($source === null) {
            return [null, true];
        }
        $tokens = $source->tokens();
        assert(count($tokens) === 1 || count($tokens) === 2, 'An alias contains a name and optionally AS.');
        return [(new IdentifierReader())->name($tokens[count($tokens) - 1]), count($tokens) === 2];
    }
}
