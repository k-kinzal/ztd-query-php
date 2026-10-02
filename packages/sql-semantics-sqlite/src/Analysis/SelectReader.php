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
use SqlSemantics\Statement\Query\Select;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
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
    public function read(Node $source, Catalog|Scope|SqliteAliasScope $catalog): Select|\SqlSemantics\Statement\Query\ScopedSelect
    {
        $input = (new Input\QueryInputReader())->read($source);
        \SqlSemantics\Statement\Validation\Check::invariant($input instanceof \SqlSemantics\Statement\Construction\Query\SelectDefinition, 'The SELECT reader requires a SELECT input.');
        return $catalog instanceof Catalog ? new Select($catalog, $input) : new \SqlSemantics\Statement\Query\ScopedSelect($catalog, $input);
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
        if ($alias === null && !$columnLabel) {
            $tokens = $expression->tokens();
            $label = substr($expression->toString(), strlen($tokens[0]->leading));
            if ($label !== $operand->toString()) {
                $alias = new Name($label, Quote::Double);
            }
        }
        $fields[] = [new Field($operand, $alias, explicitAlias: $explicit), $visibleAlias];
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
