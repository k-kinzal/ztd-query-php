<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Platform\Sqlite\IdentifierReader;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Schema\Definition\ColumnConstraint;
use SqlSemantics\Statement\Schema\Definition\ColumnNullability;
use SqlSemantics\Statement\Schema\Definition\ColumnPrimaryKey;
use SqlSemantics\Statement\Schema\Definition\ColumnUnique;
use SqlSemantics\Statement\Schema\Definition\ConflictAction;
use SqlSemantics\Statement\Schema\Definition\KeyDirection;
use SqlSemantics\Statement\Schema\Definition\SqliteColumnDefinition;

/**
 * Describes a column's declared type and constraints as independent semantic values.
 * @visibility SqlSemantics
 */
final class ColumnDefinitionReader
{
    /**
     * The resulting declaration identity is shared by the CREATE operation and later references.
     */
    public function read(Node $source, ?Node $constraints = null, bool $strict = false): SqliteColumnDefinition
    {
        Tree::assertChildren($source, ['nm', 'typetoken'], []);
        $name = Tree::child($source, ['nm']);
        assert($name !== null, 'A column declaration has a name.');
        $type = Tree::outer($source, ['typetoken'])[0];
        return new SqliteColumnDefinition((new IdentifierReader())->name($name), (new DeclaredTypeReader())->read($type, $strict), ...($constraints === null ? [] : $this->constraints($constraints)));
    }

    /**
     * Constraint labels belong to their following rule, not to a parser list node.
     * @return list<ColumnConstraint>
     */
    public function constraints(Node $source): array
    {
        $rules = [];
        $name = null;
        foreach (Tree::outer($source, ['ccons']) as $constraint) {
            $tokens = $constraint->tokens();
            if (\SqlSemantics\Statement\Identifier\Ascii::upper($tokens[0]->text) === 'CONSTRAINT') {
                $name = (new IdentifierReader())->name(Tree::outer($constraint, ['nm'])[0]);
                continue;
            }
            $rules[] = $this->rule($constraint, $name);
            $name = null;
        }
        return $rules;
    }

    /**
     * Keeps constraint kind, order, and conflict behavior distinct.
     */
    public function rule(Node $source, ?Name $name = null): ColumnConstraint
    {
        $tokens = $source->tokens();
        $direction = Tree::child($source, ['sortorder']);
        $kind = \SqlSemantics\Statement\Identifier\Ascii::upper($tokens[0]->text);
        $kind .= $kind === 'NOT' ? ' ' . \SqlSemantics\Statement\Identifier\Ascii::upper($tokens[1]->text) : '';
        return match ($kind) {
            'NULL', 'NOT NULL' => new ColumnNullability($kind === 'NULL', $this->conflict($source), $name),
            'PRIMARY' => new ColumnPrimaryKey($direction === null ? KeyDirection::Implicit : KeyDirection::from(\SqlSemantics\Statement\Identifier\Ascii::upper(Tree::text($direction))), $this->conflict($source), Tree::child($source, ['autoinc']) !== null, $name),
            'UNIQUE' => new ColumnUnique($this->conflict($source), $name),
            default => Tree::unsupported($source, 'column constraint'),
        };
    }

    /**
     * Reads the explicit conflict policy independently of the constraint kind.
     */
    public function conflict(Node $source): ConflictAction
    {
        $policy = Tree::child($source, ['onconf']);
        return $policy === null ? ConflictAction::Implicit : ConflictAction::from(\SqlSemantics\Statement\Identifier\Ascii::upper(Tree::text(Tree::outer($policy, ['resolvetype'])[0])));
    }
}
