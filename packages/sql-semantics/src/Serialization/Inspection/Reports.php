<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Inspection;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Query\Inspection\Engine\EngineSelection;
use SqlSemantics\Model\Query\Inspection\Profile\ProfileCategory;
use SqlSemantics\Model\Query\Inspection\Profile\ProfileLimit;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Inspection\Server;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Statements;

/**
 * Writes engine reports, session profiles, and parse tree requests.
 * @visibility SqlSemantics
 */
final class Reports
{
    /**
     * An engine name is quoted as an identifier; a parsed statement is written by its own serializer.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Server\ShowEngineReportStatement => new Tree('show', [Build::keyword('SHOW ENGINE'), $statement->engine instanceof EngineSelection ? Build::keyword($statement->engine->value) : Build::identifier([$statement->engine], Dialect::MySql), Build::keyword($statement->report->value)]),
            $statement instanceof Server\ShowProfilesStatement => Build::keyword('SHOW PROFILES'),
            $statement instanceof Server\ShowProfileStatement => new Tree('show', [
                Build::keyword('SHOW PROFILE'),
                ...($statement->categories === [] ? [] : [Build::separated(array_map(static fn (ProfileCategory $category): Tree => Build::keyword($category->value), $statement->categories))]),
                ...($statement->query === null ? [] : [Build::keyword('FOR QUERY'), Expressions::write($statement->query)]),
                ...self::limit($statement->limit),
            ]),
            $statement instanceof Server\ShowParseTreeStatement => new Tree('show', [Build::keyword('SHOW PARSE_TREE'), Statements::write($statement->statement)]),
            default => null,
        };
    }

    /**
     * @return list<Tree> LIMIT with the count and optional OFFSET, or nothing
     */
    public static function limit(?ProfileLimit $limit): array
    {
        if ($limit === null) {
            return [];
        }
        return [Build::keyword('LIMIT'), Expressions::write($limit->count), ...($limit->offset === null ? [] : [Build::keyword('OFFSET'), Expressions::write($limit->offset)])];
    }
}
