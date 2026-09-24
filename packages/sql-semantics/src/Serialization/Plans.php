<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization;

use SqlSemantics\Model\Plan;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Plan\ExplainConnectionStatement;
use SqlSemantics\Model\Statement\Plan\ExplainStatement;

/**
 * Writes classified plan instructions around their bound operation.
 * @visibility SqlSemantics
 */
final class Plans
{
    /**
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function write(ExplainStatement|ExplainConnectionStatement|\SqlSemantics\Model\Statement\Plan\ExplainInDatabaseStatement $statement): Tree
    {
        if ($statement instanceof ExplainConnectionStatement) {
            return new Tree('explain-connection', [Build::keyword('EXPLAIN'), ...self::format($statement->format), Build::keyword('FOR CONNECTION'), Build::keyword($statement->connection->spelling)]);
        }
        if ($statement instanceof \SqlSemantics\Model\Statement\Plan\ExplainInDatabaseStatement) {
            return new Tree('explain-database', [self::mysql($statement->options), Build::keyword('FOR DATABASE'), Build::identifier([$statement->database], \SqlSemantics\Dialect::MySql), Statements::write($statement->statement)]);
        }
        $options = $statement->options;
        $prefix = match (true) {
            $options instanceof Plan\SqlitePlan => Build::keyword($options->value),
            $options instanceof Plan\MySqlPlan => self::mysql($options),
            $options instanceof Plan\PostgreSqlPlan => self::postgres($options),
            default => throw new \SqlSemantics\Model\Validation\InvalidStructure('Unclassified EXPLAIN options.'),
        };
        return new Tree('explain', [$prefix, Statements::write($statement->statement)]);
    }

    /**
     * Writes the MySQL EXPLAIN keyword with its execution, detail, format and INTO options.
     */
    public static function mysql(Plan\MySqlPlan $options): Tree
    {
        return new Tree('mysql-plan', [
            Build::keyword('EXPLAIN'),
            ...($options->analyze ? [Build::keyword('ANALYZE')] : []),
            ...($options->extended ? [Build::keyword('EXTENDED')] : []),
            ...($options->partitions ? [Build::keyword('PARTITIONS')] : []),
            ...self::format($options->format),
            ...($options->variable === null ? [] : [Build::keyword('INTO'), Scalar\ReferenceExpressions::variable($options->variable, \SqlSemantics\Schema\VariableScope::User, \SqlSemantics\Dialect::MySql)]),
        ]);
    }

    /**
     * @return list<Tree>
     */
    public static function format(Plan\MySqlFormat $format): array
    {
        return $format === Plan\MySqlFormat::Default ? [] : [Build::keyword('FORMAT'), Build::keyword('='), Build::keyword($format->value)];
    }

    /**
     * Writes PostgreSQL EXPLAIN flags, output format, and serialization-cost options.
     */
    public static function postgres(Plan\PostgreSqlPlan $options): Tree
    {
        $flags = ['ANALYZE' => $options->analyze, 'VERBOSE' => $options->verbose, 'COSTS' => $options->costs, 'SETTINGS' => $options->settings, 'GENERIC_PLAN' => $options->genericPlan, 'BUFFERS' => $options->buffers, 'WAL' => $options->wal, 'TIMING' => $options->timing, 'SUMMARY' => $options->summary, 'MEMORY' => $options->memory];
        $items = [];
        foreach ($flags as $name => $value) {
            if ($value !== null) {
                $items[] = Build::keyword($name . ($value ? ' TRUE' : ' FALSE'));
            }
        }
        $items[] = Build::keyword('SERIALIZE ' . $options->serialization->value);
        $items[] = Build::keyword('FORMAT ' . $options->format->value);
        return new Tree('postgres-plan', [Build::keyword('EXPLAIN'), Build::parentheses(Build::separated($items))]);
    }
}
