<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition as Statement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Classifies table and column alterations with operation-specific operands.
 *
 * @visibility SqlSemantics
 */
final class AlterTableBinder
{
    /**
     * Binds SQLite's individual table-alteration forms.
     * @throws UnclassifiedSql
     */
    public function bind(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        if ($context->tables->identifiers->dialect !== \SqlSemantics\Dialect::Sqlite) {
            return null;
        }
        $node = Tree::outer($source, ['fullname', 'add_column_fullname'])[0] ?? null;
        if ($node === null) {
            throw new UnclassifiedSql('An ALTER TABLE requires its target.');
        }
        $name = new QualifiedName($context->tables->identifiers->parts($node));
        $tokens = $source->tokens();
        $words = array_map(static fn ($token): string => strtoupper($token->text), $tokens);
        $keywords = Tree::keywords($source);
        $rename = array_search('RENAME', $words, true);
        $to = array_search('TO', $words, true);
        if (in_array('RENAME', $keywords, true) && $rename !== false && $to !== false && isset($tokens[$to + 1])) {
            $newName = $context->tables->identifiers->name($tokens[$to + 1]);
            return $to === $rename + 1 ? new Statement\RenameTableStatement($origin, $name, $newName) : new Statement\RenameColumnStatement($origin, $name, $context->tables->identifiers->name($tokens[$to - 1]), $newName);
        }
        if (in_array('DROP', $keywords, true)) {
            return new Statement\DropColumnStatement($origin, $name, $context->tables->identifiers->name($tokens[count($tokens) - 1]));
        }
        $column = Tree::outer($source, ['columnname'])[0] ?? null;
        if ($column !== null) {
            return self::addColumn($origin, $source, $name, $column, $context);
        }
        throw new UnclassifiedSql('Unclassified table alteration: ' . Tree::text($source));
    }

    /**
     * Binds a newly declared column and its own integrity constraints.
     * @throws \SqlSemantics\InvalidSql
     */
    public static function addColumn(Origin $origin, Node $source, QualifiedName $name, Node $column, QueryContext $context): Statement\AddColumnStatement
    {
        $target = $context->tables->resolve($name->parts, $source);
        [$parsed, $constraints] = (new \SqlSemantics\Ast\ColumnReader($context->tables->identifiers))->read($column, Tree::outer($source, ['ccons']));
        $newColumn = new \SqlSemantics\Schema\ColumnDefinition($parsed->name, $parsed->type, $parsed->nullability, $parsed->source);
        $table = new \SqlSemantics\Schema\TableDefinition($target->schema, $target->name, [...$target->columns, $newColumn], $target->constraints, $target->source);
        $scope = new \SqlSemantics\Binding\Scope($context->tables->identifiers, [new \SqlSemantics\Model\Relation\TableReference('declaration', 'declaration', $table, new QualifiedName($table->schema === '' ? [$table->name] : [$table->schema, $table->name]), null, $source)], queries: $context);
        $column = \SqlSemantics\Binding\Schema\ColumnBinder::bind($parsed, $scope);
        $boundConstraints = array_map(static fn ($constraint): \SqlSemantics\Schema\TableConstraint => \SqlSemantics\Binding\Schema\ConstraintBinder::bind($constraint, $scope), $constraints);
        if (array_filter($boundConstraints, static fn ($constraint): bool => $constraint instanceof \SqlSemantics\Schema\Constraint\PrimaryKey || $constraint instanceof \SqlSemantics\Schema\Constraint\UniqueKey) !== []) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::AddedColumnKey, $source);
        }
        return new Statement\AddColumnStatement($origin, $name, $column, $boundConstraints);
    }
}
