<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\PostgreSqlTable;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\SchemaReader;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Schema\TablePropertiesBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Binding\Statement\Definition\Relation\ForeignTables;
use SqlSemantics\Binding\Statement\Definition\Relation\PartitionActions;
use SqlSemantics\Binding\Statement\Definition\Relation\PartitionColumns;
use SqlSemantics\Binding\Statement\Definition\Relation\RelationAlterations;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Catalog\Kind\RelationKind;
use SqlSemantics\Model\Definition\Relation\Foreign\PartitionColumn;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Table\PostgreSqlProperties;
use SqlSemantics\Schema\TableConstraint;

/**
 * Binds the PostgreSQL CREATE TABLE forms whose columns come from elsewhere: PARTITION OF a partitioned table and OF a composite type.
 * @visibility SqlSemantics
 */
final class PostgreSqlTables
{
    /**
     * Returns null for other dialects, other statements, and ordinary table declarations.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $statement, QueryContext $context): Statement\CreatePartitionStatement|Statement\CreateTypedTableStatement|null
    {
        if ($origin->dialect !== Dialect::PostgreSql || $statement->name !== 'CreateStmt') {
            return null;
        }
        $names = array_values(array_filter($statement->children, static fn ($child): bool => $child instanceof Node && $child->name === 'qualified_name'));
        $type = Tree::child($statement, ['any_name']);
        if (!isset($names[1]) && $type === null) {
            return null;
        }
        $name = ObjectAddresses::name($names[0], $context, 3);
        $ifNotExists = array_filter($statement->children, static fn ($child): bool => $child instanceof Token && $child->name === 'IF_P') !== [];
        $parent = isset($names[1]) ? ObjectAddresses::name($names[1], $context, 3) : null;
        $scope = $parent === null ? new Scope($context->tables->identifiers, queries: $context) : RelationAlterations::scope($origin, $names[1], $context, RelationKind::Table, $parent);
        [$columns, $constraints, $exclusions] = self::elements($statement, $scope, $context);
        $properties = self::properties($statement, $scope, $context);
        try {
            if ($parent !== null) {
                $bound = PartitionActions::bound(Tree::child($statement, ['PartitionBoundSpec']) ?? throw new UnclassifiedSql('A partition requires its bound.'), $scope);
                return new Statement\CreatePartitionStatement($origin, $name, $parent, $bound, $columns, $constraints, $exclusions, $properties, $ifNotExists);
            }
            return new Statement\CreateTypedTableStatement($origin, $name, ObjectAddresses::name($type ?? $statement, $context, 2), $columns, $constraints, $exclusions, $properties, $ifNotExists);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::ColumnOverride, $statement, $error);
        }
    }

    /**
     * Reads the column overrides, table constraints, and EXCLUDE constraints in written order; no constraint may adopt an existing index.
     *
     * @return array{list<PartitionColumn>, list<TableConstraint>, list<\SqlSemantics\Model\Definition\Relation\Constraint\ExclusionConstraint>}
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function elements(Node $statement, Scope $scope, QueryContext $context): array
    {
        $columns = [];
        $constraints = [];
        $exclusions = [];
        foreach (Tree::outer($statement, ['TypedTableElement']) as $element) {
            $constraint = Tree::child($element, ['TableConstraint']);
            if ($constraint === null) {
                $columns[] = PartitionColumns::read(Tree::child($element, ['columnOptions']) ?? throw new UnclassifiedSql('A typed element is a column or a constraint.'), $scope);
                continue;
            }
            $existing = Tree::outer($constraint, ['ExistingIndex'])[0] ?? null;
            if ($existing !== null) {
                throw new InvalidSql(InputViolation::ExistingIndexConstraint, $existing);
            }
            if (Exclusions::excludes($constraint)) {
                $exclusions[] = Exclusions::bind($constraint, $scope, $context);
                continue;
            }
            $constraints[] = ForeignTables::constraint($constraint, $scope, $context);
        }
        return [$columns, $constraints, $exclusions];
    }

    /**
     * Reads persistence, partitioning, access method, storage parameters, commit action, and tablespace; the elements are checked against the parent scope instead of the declaration, so declaration diagnostics are discarded.
     *
     * @throws InvalidSql
     */
    public static function properties(Node $statement, Scope $scope, QueryContext $context): PostgreSqlProperties
    {
        $reader = new SchemaReader($context->tables->identifiers, $context->tables->defaultSchema, static function (string $reason, string $message, Node $source): void {
        });
        $properties = TablePropertiesBinder::bind($reader->table($statement), $scope);
        return $properties instanceof PostgreSqlProperties ? $properties : new PostgreSqlProperties();
    }
}
