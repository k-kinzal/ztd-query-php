<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Semantic\Scope;
use SqlSemantics\Semantic\Statement\InsertRows;
use SqlSemantics\Semantic\Statement\InsertSelect;
use SqlSemantics\Semantic\Statement\InsertTarget;
use SqlSemantics\Semantic\Statement\ValuesRow;

/**
 * Constructs INSERT variants after complete dialect-specific lowering.
 * @visibility SqlSemantics
 */
final class InsertReader
{
    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(public readonly Catalog $catalog)
    {
    }

    /**
     * @param list<Node> $columns
     */
    public function target(Node $table, array $columns): InsertTarget
    {
        $names = new Names($this->catalog->identifiers);
        $relation = (new RelationReader($this->catalog))->table($table, $names->parts($table), null);
        $fields = [];
        foreach ($columns as $column) {
            $parts = $names->parts($column);
            if (count($parts) !== 1) {
                Tree::unsupported($column, 'INSERT column');
            }
            $fields[] = $parts[0];
        }
        return new InsertTarget($relation, ...$fields);
    }

    /**
     * @param list<Node> $rows
     */
    public function values(InsertTarget $target, array $rows): InsertRows
    {
        assert($rows !== [], 'An INSERT VALUES requires a lowered row.');
        $scope = new Scope($this->catalog->identifiers->dialect);
        $reader = new ExpressionReader();
        $result = [];
        foreach ($rows as $row) {
            $expressions = Tree::outer($row, $scope->dialect->platform()->syntax()->nodes('expression'));
            $consumed = [];
            foreach ($expressions as $expression) {
                foreach ($expression->tokens() as $token) {
                    $consumed[spl_object_id($token)] = true;
                }
            }
            foreach ($row->tokens() as $token) {
                if (!isset($consumed[spl_object_id($token)]) && !in_array($token->text, ['(', ')', ','], true)) {
                    Tree::unsupported($token, 'VALUES input');
                }
            }
            $result[] = new ValuesRow($scope, ...array_map(fn (Node $expr) => $reader->read($expr, $scope), $expressions));
        }
        return new InsertRows($target, $result[0], ...array_slice($result, 1));
    }

    /**
     * Constructs an INSERT with an independently scoped SELECT source.
     */
    public function select(InsertTarget $target, Node $source): InsertSelect
    {
        return new InsertSelect($target, (new SelectReader($this->catalog))->read($source));
    }
}
