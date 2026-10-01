<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Platform\Sqlite\IdentifierReader;
use SqlSemantics\Statement\Expression\BooleanReference;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Identifier\Quote;
use SqlSemantics\Statement\Projection\Field;
use SqlSemantics\Statement\Projection\Fields;
use SqlSemantics\Statement\Query\Quantifier;
use SqlSemantics\Statement\Query\Select;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\SemanticGraph;

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
        $outputs = [];
        $aliases = [];
        foreach ($this->projection($projection, $scope) as [$field, $visibleAlias]) {
            $outputs[] = $field;
            if ($visibleAlias) {
                $aliases[] = $field;
            }
        }
        $fields = new Fields($scope, ...$outputs);
        $predicate = Tree::child($select, ['where_opt']);
        $where = $predicate === null ? null : (new ExpressionReader($fields, ...$aliases))->read(Tree::outer($predicate, ['expr'])[0], $scope);
        foreach ($where === null ? [] : (new SemanticGraph())->conditionalAliases($where) as $reference) {
            if (($fields->matchingAliases($reference->alias->name->value)[0] ?? null) !== $reference->alias->field) {
                Tree::unsupported($select, 'conditional alias affected by computed output naming');
            }
        }
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
     * @return non-empty-list<array{Field, bool}>
     */
    public function projection(Node $source, Scope $scope): array
    {
        Tree::assertChildren($source, ['sclp', 'scanpt', 'expr', 'as'], []);
        $prefix = Tree::child($source, ['sclp']);
        $fields = $prefix === null ? [] : $this->projection(Tree::outer($prefix, ['selcollist'])[0], $scope);
        $expression = Tree::child($source, ['expr']);
        assert($expression !== null, 'An expression projection has an expression.');
        [$alias, $explicit] = $this->alias(Tree::child($source, ['as']));
        $visibleAlias = $alias !== null;
        $operand = (new ExpressionReader())->read($expression, $scope);
        $columnLabel = $operand instanceof ColumnReference || ($operand instanceof BooleanReference && !$operand->column->resolution instanceof \SqlSemantics\Statement\Reference\MissingColumn);
        $derived = null;
        if ($alias === null && !$columnLabel) {
            $tokens = $expression->tokens();
            $label = substr($expression->toString(), strlen($tokens[0]->leading));
            $derived = new Name($label, Quote::Double);
            if ($label !== $operand->toString()) {
                $alias = $derived;
                $derived = null;
            }
        }
        $fields[] = [new Field($operand, $alias, derivedName: $derived, explicitAlias: $explicit), $visibleAlias];
        return $fields;
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
