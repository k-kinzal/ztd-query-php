<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Declaration\IndexDefinition;
use SqlSemantics\Ast\Declaration\IndexElement;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;

/**
 * Reads standalone and table-local index declarations.
 *
 * @visibility SqlSemantics
 */
final class IndexReader
{
    /**
     * Uses the declaration's identifier rules and default namespace.
     */
    public function __construct(public readonly Identifiers $identifiers, public readonly string $defaultSchema)
    {
    }

    /**

     * Returns null for statements that do not declare an index.

     */
    public function read(Node $source): ?IndexDefinition
    {
        $node = array_values(array_filter(Tree::outer($source, ['IndexStmt', 'create_index_stmt']), Tree::hasTokens(...)))[0] ?? $source;
        $tokens = $node->tokens();
        $words = array_map(static fn ($token): string => strtoupper($token->text), $tokens);
        if (($words[0] ?? '') !== 'CREATE' || !in_array($words[1] ?? '', ['INDEX', 'UNIQUE', 'FULLTEXT', 'SPATIAL'], true)) {
            return null;
        }
        $index = array_search('INDEX', $words, true);
        $on = array_search('ON', $words, true);
        if ($index === false || $on === false) {
            return null;
        }
        $header = array_slice($tokens, $index + 1, $on - $index - 1);
        $names = [];
        foreach ($header as $token) {
            if (strtoupper($token->text) === 'USING') {
                break;
            }
            if (!in_array(strtoupper($token->text), ['CONCURRENTLY', 'IF', 'NOT', 'EXISTS', '.'], true)) {
                $names[] = $this->identifiers->name($token);
            }
        }
        $single = Tree::child($node, $this->identifiers->dialect === \SqlSemantics\Dialect::MySql ? ['ident'] : ['name']);
        if ($single !== null && Tree::hasTokens($single)) {
            $names = [$this->identifiers->name($single->tokens()[0])];
        }
        $table = $this->target($node, array_slice($tokens, $on + 1));
        $schema = count($names) > 1 ? $names[0] : (count($table) > 1 ? $table[0] : $this->defaultSchema);
        return $this->definition($node, $schema, $names === [] ? null : $names[count($names) - 1], count($table) === 1 ? ($schema === '' ? $table : [$schema, $table[0]]) : $table, IndexKeys::read($node, $this->identifiers));
    }

    /**
     * Reads the table production before considering dialect-specific token forms.
     * @param list<\SqlParser\Lexer\Token> $tokens Tokens following ON
     * @return list<string>
     */
    public function target(Node $node, array $tokens): array
    {
        $relation = Tree::outer($node, ['relation_expr'])[0] ?? null;
        $name = $relation === null ? null : (Tree::outer($relation, ['qualified_name'])[0] ?? null);
        if ($name !== null) {
            return $this->identifiers->parts($name);
        }
        $table = [];
        foreach ($tokens as $token) {
            if (in_array(strtoupper($token->text), ['(', 'USING'], true)) {
                break;
            }
            if ($token->text !== '.') {
                $table[] = $this->identifiers->name($token);
            }
        }
        return $table;
    }

    /**
     * @param list<string> $table
     * @return list<IndexDefinition>
     */
    public function table(Node $source, array $table): array
    {
        $indexes = [];
        foreach ((new \SqlSemantics\Ast\ConstraintGroups())->read(Tree::outer($source, ['table_constraint_def', 'key_def', 'TableConstraint', 'tcons'])) as $node) {
            $tokens = $node->tokens();
            $constraintName = null;
            if (strtoupper($tokens[0]->text ?? '') === 'CONSTRAINT') {
                $unnamed = in_array(strtoupper($tokens[1]->text ?? ''), ['PRIMARY', 'UNIQUE', 'FOREIGN', 'CHECK'], true);
                $constraintName = $unnamed ? null : $this->identifiers->name($tokens[1]);
                $tokens = array_slice($tokens, $unnamed ? 1 : 2);
            }
            if (!in_array(strtoupper($tokens[0]->text ?? ''), ['KEY', 'INDEX', 'FULLTEXT', 'SPATIAL'], true)) {
                continue;
            }
            $nameNode = array_values(array_filter(Tree::outer($node, ['opt_index_name_and_type', 'opt_ident', 'opt_constraint_name']), Tree::hasTokens(...)))[0] ?? null;
            $name = $nameNode === null ? null : (array_values(array_filter(Tree::outer($nameNode, ['ident']), Tree::hasTokens(...)))[0] ?? null);
            $indexes[] = $this->definition($node, $table[0], $name === null ? $constraintName : $this->identifiers->parts($name)[0], $table, IndexKeys::read($node, $this->identifiers));
        }
        return $indexes;
    }

    /**
     * @param list<string> $table
     * @param list<IndexElement> $elements
     */
    public function definition(Node $source, string $schema, ?string $name, array $table, array $elements): IndexDefinition
    {
        $tokens = $source->tokens();
        $words = array_map(static fn ($token): string => strtoupper($token->text), $tokens);
        $using = array_values(array_filter(Tree::outer($source, ['access_method_clause', 'index_type_clause', 'key_using_alg', 'opt_index_name_and_type']), static fn (Node $node): bool => Tree::hasTokens($node) && ($node->name !== 'opt_index_name_and_type' || in_array(strtoupper($node->tokens()[count($node->tokens()) - 2]->text ?? ''), ['USING', 'TYPE'], true))))[0] ?? null;
        $method = $using === null ? null : ($this->identifiers->dialect === \SqlSemantics\Dialect::MySql ? strtolower($using->tokens()[count($using->tokens()) - 1]->text) : $this->identifiers->name($using->tokens()[count($using->tokens()) - 1]));
        $include = array_values(array_filter(Tree::outer($source, ['opt_include', 'opt_c_include']), Tree::hasTokens(...)))[0] ?? null;
        $columns = $include === null ? [] : array_values(array_filter(array_slice($this->identifiers->parts($include), 1), static fn (string $part): bool => !in_array($part, ['(', ')', ','], true)));
        $where = array_values(array_filter(Tree::outer($source, ['where_clause', 'where_opt']), Tree::hasTokens(...)))[0] ?? null;
        $predicate = $where === null ? null : (array_values(array_filter(Tree::outer($where, ['a_expr', 'expr']), Tree::hasTokens(...)))[0] ?? null);
        $options = OptionReader::read($source, $this->identifiers, ['index_params', 'key_list_with_expression', 'key_list', 'sortlist', 'columnList', 'where_clause', 'where_opt']);
        if (str_contains(' ' . implode(' ', array_slice($words, 0, 9)) . ' ', ' IF NOT EXISTS ')) {
            $options['if_not_exists'] = true;
        }
        if (in_array('FULLTEXT', array_slice($words, 0, 3), true) || in_array('SPATIAL', array_slice($words, 0, 3), true)) {
            $options['kind'] = in_array('FULLTEXT', array_slice($words, 0, 3), true) ? 'fulltext' : 'spatial';
        }
        return new IndexDefinition($schema, $name, $table, $elements, in_array('UNIQUE', array_slice($words, 0, 4), true) || in_array('PRIMARY', array_slice($words, 0, 4), true), $method, $columns, $predicate, $source, $options);
    }
}
