<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Procedural;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\TableOccurrence;
use SqlSemantics\Binding\Scalar\VariableBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Write\AssignmentBinder;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Maintenance\IndexCache\NamedPartitions;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Loading;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Assignment;

/**
 * Binds LOAD DATA and LOAD XML, separating row loads from bulk loads (ALGORITHM = BULK) because each accepts different clauses.
 * @visibility SqlSemantics
 */
final class Loads
{
    /**
     * Resolves the target table and binds the columns, variables and SET items against it.
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $node, QueryContext $context): BoundStatement
    {
        $table = TableOccurrence::resolve($node, $context, $origin->scopeId);
        if (!$table instanceof TableReference) {
            Tree::invalid($node, 'loaded table');
        }
        $scope = new Scope($context->tables->identifiers, [$table], queries: $context);
        $file = LoadClauses::token((Tree::child($node, ['TEXT_STRING_filesystem']) ?? Tree::invalid($node, 'loaded file'))->tokens()[0], $node);
        $source = Loading\LoadSource::from(self::word($node, 'load_source_type') === '' ? 'INFILE' : self::word($node, 'load_source_type'));
        $duplicates = self::word($node, 'opt_duplicate');
        $partitions = Tree::child($node, ['opt_use_partition']);
        $names = $partitions === null ? [] : array_map(static fn (Node $name): string => $context->tables->identifiers->name($name->tokens()[0]), Tree::outer($partitions, ['ident']));
        $layout = LoadClauses::layout($node, $context);
        try {
            if (self::word($node, 'opt_load_algorithm') !== '') {
                return self::bulk($origin, $node, $source, $file, $table, $duplicates === '' ? null : Loading\DuplicateRows::from($duplicates), $names === [] ? null : new NamedPartitions($names), $layout);
            }
            if (LoadClauses::fileCount($node) !== null || $source === Loading\LoadSource::Url || self::word($node, 'opt_compression_algorithm') !== '') {
                throw new InvalidSql(InputViolation::LoadOption, $node);
            }
            $scheduling = self::word($node, 'load_data_lock');
            return new Loading\LoadFileStatement($origin, Loading\LoadFormat::from(strtoupper(self::word($node, 'data_or_xml'))), $file, $table, $scheduling === '' ? null : Loading\LoadScheduling::from($scheduling), self::word($node, 'opt_local') !== '', $source, $duplicates === '' ? null : Loading\DuplicateRows::from($duplicates), $names === [] ? null : new NamedPartitions($names), $layout, self::targets($node, $scope), self::assignments($node, $scope));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::LoadOption, $node, $error);
        }
    }

    /**
     * Rejects the row-load clauses the bulk loader refuses before building the bulk load.
     * @throws InvalidSql
     * @throws InvalidStructure
     */
    public static function bulk(Origin $origin, Node $node, Loading\LoadSource $source, \SqlSemantics\Model\Scalar\Value\Literal $file, TableReference $table, ?Loading\DuplicateRows $duplicates, ?NamedPartitions $partitions, Loading\LoadLayout $layout): Loading\BulkLoadStatement
    {
        $fields = Tree::child($node, ['opt_field_or_var_spec']);
        if (strtoupper(self::word($node, 'data_or_xml')) === 'XML' || self::word($node, 'opt_local') !== '' || ($fields !== null && Tree::outer($fields, ['field_or_var']) !== []) || self::word($node, 'opt_load_data_set_spec') !== '') {
            throw new InvalidSql(InputViolation::LoadOption, $node);
        }
        $compression = Tree::child($node, ['opt_compression_algorithm']);
        return new Loading\BulkLoadStatement($origin, $source, $file, $table, LoadClauses::fileCount($node), self::word($node, 'opt_source_order') !== '', $duplicates, $partitions, $layout, $compression === null || Tree::text($compression) === '' ? null : LoadClauses::literal($compression), LoadClauses::number($node, 'opt_load_parallel'), LoadClauses::memory($node));
    }

    /**
     * Returns the uppercase text of an optional clause, or an empty string when it is absent.
     */
    public static function word(Node $node, string $rule): string
    {
        $clause = Tree::child($node, [$rule]);
        return $clause === null ? '' : strtoupper(Tree::text($clause));
    }

    /**
     * Binds each column and user variable receiving an input field; an empty list means every column.
     * @return list<Expression>
     */
    public static function targets(Node $node, Scope $scope): array
    {
        $targets = [];
        foreach (Tree::outer(Tree::child($node, ['opt_field_or_var_spec']) ?? new Node('none', 0, []), ['field_or_var']) as $target) {
            $column = Tree::child($target, ['simple_ident_nospvar']);
            $targets[] = $column === null ? (new VariableBinder())->bind($target, $scope) : (new AssignmentBinder())->target($column, $scope);
        }
        return $targets;
    }

    /**
     * Binds each SET item against the loaded table.
     * @return list<Assignment>
     * @throws InvalidSql
     */
    public static function assignments(Node $node, Scope $scope): array
    {
        return array_map(static fn (Node $item): Assignment => (new AssignmentBinder())->assignment($item, $scope), Tree::outer(Tree::child($node, ['opt_load_data_set_spec']) ?? new Node('none', 0, []), ['load_data_set_elem']));
    }
}
