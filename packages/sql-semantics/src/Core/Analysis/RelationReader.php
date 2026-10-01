<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Model\JoinKind;
use SqlSemantics\Semantic\Name;
use SqlSemantics\Semantic\QualifiedName;
use SqlSemantics\Semantic\Relation\Join;
use SqlSemantics\Semantic\Relation\TableReference;
use SqlSemantics\Semantic\Scope;

/**
 * Resolves occurrences before reading expressions in their scopes.
 * @visibility SqlSemantics
 */
final class RelationReader
{
    /**
     * The immutable semantic value shared by dependent expressions.
     */
    public readonly Names $names;

    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(public readonly Catalog $tables)
    {
        $this->names = new Names($tables->identifiers);
    }

    /**
     * Builds the visible relation namespace before reading its expressions.
     */
    public function scope(?Node $from): Scope
    {
        $dialect = $this->tables->identifiers->dialect;
        $sources = [];
        if ($from !== null) {
            foreach (Tree::outer($from, $dialect->platform()->syntax()->nodes('relation')) as $node) {
                $sources[] = $this->relation($node);
            }
            if ($sources === [] && $from->tokens() !== []) {
                Tree::unsupported($from, 'FROM');
            }
        }
        return new Scope($dialect, ...$sources);
    }

    /**
     * Lowers one named table or joined relation while preserving occurrence identity.
     */
    public function relation(Node $node): TableReference|Join
    {
        return $this->tables->identifiers->dialect->platform()->relations()->relation($node, $this);
    }

    /**
     * @param non-empty-list<Name> $parts
     */
    public function table(Node $source, array $parts, ?Node $aliasNode): TableReference
    {
        if (count($parts) > 2) {
            Tree::unsupported($source, 'table qualification');
        }
        $alias = null;
        if ($aliasNode !== null && $aliasNode->tokens() !== []) {
            $tokens = $aliasNode->tokens();
            if (strtoupper($tokens[0]->text) === 'AS') {
                $tokens = array_slice($tokens, 1);
            }
            if (count($tokens) !== 1) {
                Tree::unsupported($aliasNode, 'table alias');
            }
            $alias = $this->names->name($tokens[0]);
        }
        $name = new QualifiedName($parts[count($parts) - 1], count($parts) === 2 ? $parts[0] : null);
        return new TableReference($this->tables->identifiers->dialect, $name, $alias, $this->tables->find($name), $this->tables->tables !== null);
    }

    /**
     * Interprets a join operation and rejects unmodeled join semantics.
     */
    public function kind(string $text, Node $source): JoinKind
    {
        $text = strtoupper($text);
        if (str_contains($text, 'NATURAL') || str_contains($text, 'STRAIGHT')) {
            Tree::unsupported($source, 'join operation');
        }
        return match (true) {
            str_contains($text, 'LEFT') => JoinKind::Left,
            str_contains($text, 'RIGHT') => JoinKind::Right,
            str_contains($text, 'FULL') => JoinKind::Full,
            str_contains($text, 'CROSS'), $text === ',' => JoinKind::Cross,
            default => JoinKind::Inner,
        };
    }

    /**
     * Resolves a match condition before applying outer-join NULL extension.
     */
    public function join(TableReference|Join $left, TableReference|Join $right, JoinKind $kind, ?Node $condition, Node $source): Join
    {
        $scope = new Scope($this->tables->identifiers->dialect, $left, $right);
        $expression = $condition === null ? null : (new ExpressionReader())->read($condition, $scope);
        if ($kind !== JoinKind::Cross && $expression === null) {
            Tree::unsupported($source, 'join without a match condition');
        }
        return new Join($kind, $left, $right, $expression);
    }
}
