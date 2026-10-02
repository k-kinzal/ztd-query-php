<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use LogicException;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\ValueReader;
use SqlSemantics\Core\Ast\ColumnAttributes;
use SqlSemantics\Core\Ast\Identifiers;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Policy\SchemaRules as Contract;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Statement\Declaration\ColumnDefinition;
use SqlSemantics\Statement\Declaration\ConstraintKind;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TableConstraint;
use SqlSemantics\Statement\Declaration\TableDefinition;
use SqlSemantics\Statement\Element;

/**
 * Sqlite SchemaRules implementation.
 *
 * @visibility SqlSemantics
 */
final class SchemaRules implements Contract
{
    /**
     * Implicit namespaces searched before the session path for declared tables.
     * @return list<string>
     */
    public function implicitSchemas(): array
    {
        return ['temp'];
    }

    /**
     * @return list<string>
     */
    public function keyColumns(Node $constraint, Identifiers $identifiers): array
    {
        $expressions = Tree::outer($constraint, ['expr']);
        if ($expressions !== []) {
            return array_map(fn (Node $expression): string => $identifiers->name($this->keyColumn($expression)), $expressions);
        }
        return \SqlSemantics\Core\Ast\TokenGroups::keyNames(\SqlSemantics\Core\Ast\TokenGroups::parentheses($constraint->tokens())[0] ?? [], $identifiers);
    }

    /**
     * NULL does not remove an explicit NOT NULL constraint.
     * @param list<Node> $attributes
     */
    public function nullability(Node $column, array $attributes, Nullability $implicit): Nullability
    {
        return in_array(Nullability::NotNull, ColumnAttributes::nulls($attributes), true) ? Nullability::NotNull : $implicit;
    }

    /**
     * Converts the default grammar's signs and bare string into expression forms.
     * @throws LogicException When the expression forms are missing
     */
    public function defaultValue(Node $attribute, ValueReader $values): Element
    {
        $expression = Tree::outer($attribute, ['expr', 'term'])[0] ?? null;
        $tokens = $attribute->tokens();
        if ($expression === null) {
            $text = (new NameRules())->name($tokens[count($tokens) - 1]);
            return $values->vocabulary->build($values->vocabulary->form('term', ['STRING']) ?? throw new LogicException('Missing string form.'), ["'" . str_replace("'", "''", $text) . "'"]);
        }
        $value = $values->read($expression);
        $sign = $tokens[1]->name ?? '';
        if (in_array($sign, ['PLUS', 'MINUS'], true)) {
            return $values->vocabulary->build($values->vocabulary->form('expr', ['PLUS|MINUS', 'expr']) ?? throw new LogicException('Missing unary form.'), [$tokens[1]->text, $value]);
        }
        return $value;
    }

    /**
     * Rejects declarations whose column state requires evaluating another relation.
     */
    public function validate(Node $source, Node $header): void
    {
        Tree::assertChildren($source, ['create_table', 'create_table_args'], []);
        Tree::assertChildren($header, ['createkw', 'temp', 'nm', 'dbnm', 'ifnotexists'], ['TABLE']);
        $arguments = Tree::child($source, ['create_table_args']);
        if ($arguments === null) {
            Tree::unsupported($source, 'table arguments');
        }
        Tree::assertChildren($arguments, ['columnlist', 'conslist_opt', 'table_option_set'], ['(', ')']);
        foreach (Tree::outer($source, ['tcons']) as $constraint) {
            if (!in_array(\SqlSemantics\Statement\Identifier\Ascii::upper($constraint->tokens()[0]->text ?? ''), ['PRIMARY', 'UNIQUE'], true)) {
                continue;
            }
            foreach (Tree::outer($constraint, ['expr']) as $expression) {
                $this->keyColumn($expression);
            }
        }
    }

    /**
     * A table key accepts a column, optionally parenthesized or collated, never an expression.
     * @throws SemanticException When a key contains an expression
     */
    public function keyColumn(Node $expression): \SqlParser\Lexer\Token
    {
        $children = Tree::significant($expression);
        if (count($children) === 1 && $children[0] instanceof Node && $children[0]->name === 'term') {
            return $this->keyColumn($children[0]);
        }
        if (count($children) === 1 && $children[0] instanceof \SqlParser\Lexer\Token && in_array($children[0]->name, ['ID', 'STRING'], true)) {
            return $children[0];
        }
        if (count($children) === 3 && $children[0] instanceof Node && $children[1] instanceof \SqlParser\Lexer\Token && $children[1]->name === 'COLLATE') {
            return $this->keyColumn($children[0]);
        }
        if (count($children) === 3 && $children[0] instanceof \SqlParser\Lexer\Token && $children[0]->name === 'LP' && $children[1] instanceof Node) {
            return $this->keyColumn($children[1]);
        }
        throw new SemanticException('invalid-key-expression', 'A table key must name columns, not expressions.', $expression);
    }

    /**
     * @return list<Node>
     */
    public function options(Node $source): array
    {
        return array_values(array_filter(Tree::outer($source, ['table_option']), static fn (Node $node): bool => $node->tokens() !== []));
    }

    /**
     * Reports table-level primary key nullability.
     */
    public function primaryOptionsNotNull(Node $source): bool
    {
        foreach ($this->options($source) as $option) {
            if (in_array(\SqlSemantics\Statement\Identifier\Ascii::upper(Tree::text($option)), ['STRICT', 'WITHOUT ROWID'], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return list<array{Node, list<Node>}>
     */
    public function columnNodes(Node $create): array
    {
        $columns = [];
        foreach ($create->find('columnlist') as $list) {
            $column = Tree::child($list, ['columnname']);
            if ($column !== null) {
                $attributes = Tree::child($list, ['carglist']);
                $columns[] = [$column, $attributes === null ? [] : Tree::outer($attributes, ['ccons'])];
            }
        }
        return array_reverse($columns);
    }

    /**
     * A sole primary key spelled exactly INTEGER, not sorted descending, aliases the rowid and is never NULL.
     *
     * @param list<string> $primary
     * @param list<TableConstraint> $constraints
     */
    public function primaryNotNull(ColumnDefinition $column, Node $declaration, array $primary, array $constraints): bool
    {
        if (count($primary) !== 1 || !TypeReader::rowidAlias($declaration)) {
            return false;
        }
        foreach ($constraints as $constraint) {
            if ($constraint->kind === ConstraintKind::PrimaryKey && $constraint->inline && $constraint->descending) {
                return false;
            }
        }
        return true;
    }

    /**
     * Selects the syntax node that owns the complete declaration.
     */
    public function schemaNode(Node $statement, Node $create): Node
    {
        return $statement;
    }

    /**
     * Returns the declaration identity used for duplicate detection.
     */
    public function tableKey(TableDefinition $table): string
    {
        return \SqlSemantics\Statement\Identifier\Ascii::lower($table->schema . "\x00" . $table->name);
    }

    /**
     * @param list<string> $parts
     * @return list<string>
     */
    public function qualify(Node $header, array $parts, Identifiers $identifiers): array
    {
        $database = Tree::child($header, ['dbnm']);
        if (Tree::child($header, ['temp']) !== null && $database === null) {
            return ['temp', ...$parts];
        }
        return $database === null ? $parts : [$parts[0], ...$identifiers->parts($database)];
    }
}
