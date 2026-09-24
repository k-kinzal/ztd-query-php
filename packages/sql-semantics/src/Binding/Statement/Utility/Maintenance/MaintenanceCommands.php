<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Utility\Maintenance;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\TableOccurrence;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Binding\Statement\Utility\QualifiedNames;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds PostgreSQL VACUUM, ANALYZE and CLUSTER with their typed options and resolved relations.
 * @visibility SqlSemantics
 */
final class MaintenanceCommands
{
    /**
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function vacuum(Origin $origin, Node $source, QueryContext $context): Statement\VacuumStatement
    {
        $options = MaintenanceSettings::vacuum($source, $context->tables->identifiers);
        try {
            return new Statement\VacuumStatement($origin, $options, self::targets($origin, $source, $context));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::MaintenanceOption, $source, $error);
        }
    }

    /**
     * @throws InvalidSql
     * @throws UnclassifiedSql
     * @throws InvalidStructure
     */
    public static function analyze(Origin $origin, Node $source, QueryContext $context): Statement\AnalyzeStatement
    {
        return new Statement\AnalyzeStatement($origin, MaintenanceSettings::analyze($source, $context->tables->identifiers), self::targets($origin, $source, $context));
    }

    /**
     * CLUSTER accepts only the VERBOSE option; the legacy index ON table form names the index first.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     * @throws InvalidStructure
     */
    public static function cluster(Origin $origin, Node $source, QueryContext $context): Statement\ClusterAllStatement|Statement\ClusterTableStatement
    {
        $verbose = MaintenanceSettings::present($source, 'opt_verbose');
        foreach (UtilityOptions::read($source, $context->tables->identifiers) as $name => $option) {
            $verbose = $name === 'verbose' ? UtilityOptions::boolean($option) : throw new InvalidSql(InputViolation::MaintenanceOption, $option[1]);
        }
        $name = Tree::child($source, ['qualified_name']);
        if ($name === null) {
            return new Statement\ClusterAllStatement($origin, $verbose);
        }
        $index = Tree::child($source, ['name']) ?? Tree::child(Tree::child($source, ['cluster_index_specification']) ?? $source, ['name']);
        $indexName = $index === null ? null : $context->tables->identifiers->name($index->tokens()[0] ?? throw new UnclassifiedSql('A clustering index requires its name.'));
        return new Statement\ClusterTableStatement($origin, self::table($origin, $name, $context), $indexName, $verbose);
    }

    /**
     * @return list<Statement\MaintenanceTarget>
     * @throws InvalidSql
     * @throws UnclassifiedSql
     * @throws InvalidStructure
     */
    public static function targets(Origin $origin, Node $source, QueryContext $context): array
    {
        $targets = [];
        foreach (Tree::outer($source, ['vacuum_relation']) as $relation) {
            $name = Tree::child($relation, ['qualified_name']) ?? throw new UnclassifiedSql('A maintenance target requires its relation name.');
            $columns = array_map(static fn (Node $column): string => $context->tables->identifiers->name($column->tokens()[0] ?? throw new UnclassifiedSql('A column requires its name.')), Tree::outer(Tree::child($relation, ['opt_name_list']) ?? $name, ['name']));
            $targets[] = new Statement\MaintenanceTarget(self::table($origin, $name, $context), $columns);
        }
        return $targets;
    }

    /**
     * Diagnoses improper qualified names before resolving the relation.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function table(Origin $origin, Node $name, QueryContext $context): TableReference
    {
        QualifiedNames::read($name, $context->tables->identifiers);
        $table = TableOccurrence::resolve($name, $context, $origin->scopeId);
        return $table instanceof TableReference ? $table : throw new UnclassifiedSql('A maintenance target is a named relation.');
    }
}
