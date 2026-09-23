<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization;

use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement;
use SqlSemantics\Model\Validation\InvalidStructure;

/**

 * Dispatches statements by their semantic form, without consulting parser syntax. @visibility SqlSemantics

 */
final class Statements
{
    /**
     * @throws InvalidStructure
     */
    public static function write(BoundStatement $statement): Tree
    {
        $execution = Definition\Statements::write($statement) ?? Execution\Statements::write($statement);
        if ($execution !== null) {
            return $execution;
        }
        return match (true) {
            $statement instanceof Statement\Maintenance\TruncateTableStatement,
            $statement instanceof Statement\Maintenance\TruncateRelationsStatement,
            $statement instanceof Statement\Maintenance\ReindexAllStatement,
            $statement instanceof Statement\Maintenance\ReindexNamedStatement,
            $statement instanceof Statement\Maintenance\ReindexObjectStatement,
            $statement instanceof Statement\Maintenance\ReindexDatabaseStatement,
            $statement instanceof Statement\Maintenance\AnalyzeAllStatement,
            $statement instanceof Statement\Maintenance\AnalyzeNamedStatement,
            $statement instanceof Statement\Maintenance\VacuumDatabaseStatement,
            $statement instanceof Statement\Maintenance\VacuumIntoStatement,
            $statement instanceof Statement\Maintenance\AttachDatabaseStatement,
            $statement instanceof Statement\Maintenance\DetachDatabaseStatement,
            $statement instanceof Statement\Maintenance\UseDatabaseStatement => Maintenance::write($statement),
            $statement instanceof BoundQuery => Query\Queries::write($statement),
            $statement instanceof Statement\InsertStatement => Insertions::write($statement),
            $statement instanceof Statement\UpdateStatement,
            $statement instanceof Statement\DeleteStatement => Mutations::write($statement),
            $statement instanceof Statement\MergeStatement => Merges::write($statement),
            $statement instanceof Statement\ConfigurationStatement => Settings::write($statement),
            default => throw new InvalidStructure('Unclassified statement serializer: ' . $statement::class),
        };
    }
}
