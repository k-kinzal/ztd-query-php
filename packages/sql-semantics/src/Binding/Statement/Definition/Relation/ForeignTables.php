<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Relation;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\ConstraintReader;
use SqlSemantics\Ast\SchemaReader;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Schema\ConstraintBinder;
use SqlSemantics\Binding\Schema\DeclarationBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Binding\Statement\Definition\ForeignOperands;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Relation\Foreign;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Schema\TableConstraint;

/**
 * Binds foreign table declarations: own columns with templates and parents, or a partition of a partitioned table.
 * @visibility SqlSemantics
 */
final class ForeignTables
{
    /**
     * Returns null for other dialects and other statements.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        if ($origin->dialect !== Dialect::PostgreSql || $source->name !== 'CreateForeignTableStmt') {
            return null;
        }
        ForeignConstraints::check($source);
        $identifiers = $context->tables->identifiers;
        $ifNotExists = array_filter($source->children, static fn ($child): bool => $child instanceof Token && $child->name === 'IF_P') !== [];
        $names = array_values(array_filter($source->children, static fn ($child): bool => $child instanceof Node && $child->name === 'qualified_name'));
        $serverNode = Tree::child($source, ['name']) ?? throw new UnclassifiedSql('A foreign table requires its server.');
        $server = $identifiers->name($serverNode->tokens()[0]);
        $optionsNode = Tree::child($source, ['create_generic_options']);
        $options = $optionsNode === null ? [] : array_map(static fn (Node $option) => ForeignOperands::option($option, $identifiers), Tree::outer($optionsNode, ['generic_option_elem']));
        if (isset($names[1])) {
            return self::partition($origin, $source, $context, $names, $server, $options, $ifNotExists);
        }
        $reader = new SchemaReader($identifiers, $context->tables->defaultSchema, $context->tables->diagnostics->report(...));
        $declared = DeclarationBinder::bind($reader->table($source), $context);
        $target = new TableReference($context->ids->relation(), $origin->scopeId, $declared, new QualifiedName($declared->schema === '' ? [$declared->name] : [$declared->schema, $declared->name]), null, $source);
        $inherits = Tree::child($source, ['OptInherit']);
        $columnOptions = [];
        foreach (Tree::outer($source, ['columnDef']) as $column) {
            $elements = Tree::outer($column, ['generic_option_elem']);
            if ($elements !== []) {
                $columnOptions[] = new Foreign\ColumnForeignOptions($identifiers->name((Tree::child($column, ['ColId']) ?? throw new UnclassifiedSql('A column requires its name.'))->tokens()[0]), Collections::nonEmpty(array_map(static fn (Node $option) => ForeignOperands::option($option, $identifiers), $elements)));
            }
        }
        return new Statement\CreateForeignTableStatement(
            $origin,
            (new \SqlSemantics\Binding\Schema\DefinitionBinder())->bind($target, new Scope($identifiers, [$target], queries: $context)),
            $server,
            $options,
            $inherits === null ? [] : array_map(static fn (Node $parent): QualifiedName => ObjectAddresses::name($parent, $context, 3), Tree::outer($inherits, ['qualified_name'])),
            array_map(static fn (Node $like): Foreign\TableTemplate => self::template($like, $context), Tree::outer($source, ['TableLikeClause'])),
            $columnOptions,
            $ifNotExists,
        );
    }

    /**
     * A LIKE clause names its template and an ordered list of INCLUDING and EXCLUDING choices.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function template(Node $like, QueryContext $context): Foreign\TableTemplate
    {
        $name = Tree::child($like, ['qualified_name']) ?? throw new UnclassifiedSql('LIKE requires its template table.');
        $selections = [];
        $including = true;
        foreach (array_slice($like->tokens(), 1 + count($name->tokens())) as $token) {
            $word = strtoupper($token->text);
            if (in_array($word, ['INCLUDING', 'EXCLUDING'], true)) {
                $including = $word === 'INCLUDING';
                continue;
            }
            $selections[] = new Foreign\TemplateSelection(Foreign\TemplateProperty::from($word), $including);
        }
        return new Foreign\TableTemplate(ObjectAddresses::name($name, $context, 3), $selections);
    }

    /**
     * A partition inherits its columns and may override their attributes and add constraints.
     * @param list<Node> $names
     * @param list<\SqlSemantics\Model\Definition\Foreign\ForeignOption> $options
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function partition(Origin $origin, Node $source, QueryContext $context, array $names, string $server, array $options, bool $ifNotExists): Statement\CreateForeignPartitionStatement
    {
        $parent = ObjectAddresses::name($names[1], $context, 3);
        $scope = RelationAlterations::scope($origin, $source, $context, \SqlSemantics\Model\Definition\Catalog\Kind\RelationKind::Table, $parent);
        $columns = [];
        $constraints = [];
        foreach (Tree::outer($source, ['TypedTableElement']) as $element) {
            $constraint = Tree::child($element, ['TableConstraint']);
            if ($constraint !== null) {
                $constraints[] = self::constraint($constraint, $scope, $context);
                continue;
            }
            $columns[] = PartitionColumns::read(Tree::child($element, ['columnOptions']) ?? throw new UnclassifiedSql('A typed element is a column or a constraint.'), $scope);
        }
        $bound = PartitionActions::bound(Tree::child($source, ['PartitionBoundSpec']) ?? throw new UnclassifiedSql('A partition requires its bound.'), $scope);
        return new Statement\CreateForeignPartitionStatement($origin, ObjectAddresses::name($names[0], $context, 3), $parent, $bound, $server, $options, $columns, $constraints, $ifNotExists);
    }

    /**
     * Table constraints of a partition bind without a declared column list.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function constraint(Node $node, Scope $scope, QueryContext $context): TableConstraint
    {
        $parsed = (new ConstraintReader($context->tables->identifiers))->read($node) ?? throw new UnclassifiedSql('Unclassified partition constraint: ' . Tree::text($node));
        return ConstraintBinder::bind($parsed, $scope);
    }
}
