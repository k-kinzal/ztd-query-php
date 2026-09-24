<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Extensibility;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Routes PostgreSQL routine, extension, language, access method, statistics, sequence, and assertion definitions.
 * @visibility SqlSemantics
 */
final class ExtensibilityCommands
{
    /**
     * Returns null for other dialects and for statements outside these definition forms.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            return null;
        }
        return match ($source->name) {
            'CreateExtensionStmt' => Extensions::create($origin, $source, $context),
            'AlterExtensionStmt' => Extensions::update($origin, $source, $context),
            'AlterExtensionContentsStmt' => Extensions::member($origin, $source, $context),
            'CreatePLangStmt' => Extensions::language($origin, $source, $context),
            'CreateAmStmt' => Extensions::accessMethod($origin, $source, $context),
            'CreateStatsStmt' => StatisticsDefinitions::create($origin, $source, $context),
            'AlterStatsStmt' => StatisticsDefinitions::target($origin, $source, $context),
            'CreateAssertionStmt' => Assertions::create($origin, $source, $context),
            'CreateSeqStmt' => Sequences::create($origin, $source, $context),
            'AlterSeqStmt' => Sequences::alter($origin, $source, $context),
            'CreateFunctionStmt' => RoutineDefinitions::create($origin, $source, $context),
            'AlterFunctionStmt' => RoutineAlterations::alter($origin, $source, $context),
            default => null,
        };
    }
}
