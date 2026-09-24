<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\MySqlTable;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\ConstraintGroups;
use SqlSemantics\Ast\ConstraintReader;
use SqlSemantics\Ast\Definition\IndexReader;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Schema\ConstraintBinder;
use SqlSemantics\Binding\Schema\IndexBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\MySqlTable\Key;
use SqlSemantics\Model\Definition\MySqlTable\Table\TableCommand;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Schema\IndexDefinition;
use SqlSemantics\Schema\TableConstraint;

/**
 * Binds the index and constraint alterations of MySQL ALTER TABLE against the altered table.
 * @visibility SqlSemantics
 */
final class KeyAlterations
{
    /**
     * @param list<string> $table Name parts of the altered table, recorded on added indexes
     */
    public function __construct(public readonly string $schema, public readonly array $table)
    {
    }

    /**
     * Binds ADD followed by an index or constraint declaration.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public function add(Node $item, Scope $scope): TableAlteration
    {
        $constraints = $this->constraints($item, $scope);
        if ($constraints !== []) {
            return new Key\AddConstraint($constraints[0]);
        }
        $index = $this->indexes($item, $scope)[0] ?? throw new UnclassifiedSql('ADD requires an index or constraint declaration.');
        return PartitionDefinitions::build(static fn (): Key\AddIndex => new Key\AddIndex($index), $item, InputViolation::TableAlteration);
    }

    /**
     * Binds the integrity constraints declared below a node.
     * @return list<TableConstraint>
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public function constraints(Node $source, Scope $scope): array
    {
        $constraints = [];
        foreach ((new ConstraintGroups())->read(Tree::outer($source, ['table_constraint_def', 'key_def'])) as $node) {
            $parsed = (new ConstraintReader($scope->identifiers))->read($node);
            if ($parsed !== null) {
                $constraints[] = ConstraintBinder::bind($parsed, $scope);
            }
        }
        return $constraints;
    }

    /**
     * Binds the plain, FULLTEXT, and SPATIAL indexes declared below a node.
     * @return list<IndexDefinition>
     */
    public function indexes(Node $source, Scope $scope): array
    {
        return array_map(static fn ($index): IndexDefinition => IndexBinder::definition($index, $scope), (new IndexReader($scope->identifiers, $this->schema))->table($source, $this->table));
    }

    /**
     * Binds DROP PRIMARY KEY, DROP FOREIGN KEY, DROP INDEX or KEY, DROP CHECK, and DROP CONSTRAINT.
     * @throws InvalidSql
     */
    public function drop(Node $item, Scope $scope): TableAlteration
    {
        $kind = self::kind($item);
        if ($kind === null) {
            return TableCommand::DropPrimaryKey;
        }
        return PartitionDefinitions::build(static fn (): Key\DropKey => new Key\DropKey(ColumnAlterations::name($item, $scope), $kind), $item, InputViolation::TableAlteration);
    }

    /**
     * Binds ALTER INDEX name VISIBLE or INVISIBLE, and ALTER CHECK or CONSTRAINT name [NOT] ENFORCED.
     * @throws InvalidSql
     */
    public function alter(Node $item, Scope $scope): TableAlteration
    {
        $name = ColumnAlterations::name($item, $scope);
        $words = array_map(static fn (Token $token): string => strtoupper($token->text), $item->tokens());
        $kind = self::kind($item) ?? Key\KeyKind::Index;
        return PartitionDefinitions::build(static fn (): TableAlteration => $kind === Key\KeyKind::Index
            ? new Key\SetIndexVisibility($name, end($words) === 'VISIBLE')
            : new Key\SetConstraintEnforcement($name, $kind, !in_array('NOT', $words, true)), $item, InputViolation::TableAlteration);
    }

    /**
     * Binds RENAME INDEX or KEY old TO new.
     * @throws InvalidSql
     */
    public function rename(Node $item, Scope $scope): Key\RenameIndex
    {
        $names = ColumnAlterations::names($item, $scope);
        return PartitionDefinitions::build(static fn (): Key\RenameIndex => new Key\RenameIndex($names[0] ?? '', $names[1] ?? ''), $item, InputViolation::TableAlteration);
    }

    /**
     * Reads the addressed kind from the keyword after the verb; null stands for PRIMARY KEY.
     */
    public static function kind(Node $item): ?Key\KeyKind
    {
        $second = Tree::significant($item)[1] ?? null;
        if ($second instanceof Node) {
            return Key\KeyKind::Index;
        }
        return match (strtoupper($second->text ?? '')) {
            'FOREIGN' => Key\KeyKind::ForeignKey,
            'CHECK' => Key\KeyKind::Check,
            'CONSTRAINT' => Key\KeyKind::Constraint,
            'PRIMARY' => null,
            default => Key\KeyKind::Index,
        };
    }
}
