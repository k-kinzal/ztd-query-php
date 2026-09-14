<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

/**
 * Former public names of classes moved into responsibility namespaces.
 *
 * A former name becomes an alias as soon as either name is autoloaded, so a
 * parameter or return type declared with a former name accepts the relocated
 * class while no implementation is loaded during Composer bootstrap.
 *
 * @var array<class-string, string>
 */
$formerNames = [
    ZtdQuery\Platform\MySql\Connection\MySqlErrorClassifier::class => 'ZtdQuery\\Platform\\MySql\\MySqlErrorClassifier',
    ZtdQuery\Platform\MySql\Connection\MySqlSessionSqlModeReflector::class => 'ZtdQuery\\Platform\\MySql\\MySqlSessionSqlModeReflector',
    ZtdQuery\Platform\MySql\Connection\Result\MySqlMysqliResultColumnTypeResolver::class => 'ZtdQuery\\Platform\\MySql\\MySqlMysqliResultColumnTypeResolver',
    ZtdQuery\Platform\MySql\Connection\Result\MySqlPdoResultColumnTypeResolver::class => 'ZtdQuery\\Platform\\MySql\\MySqlPdoResultColumnTypeResolver',
    ZtdQuery\Platform\MySql\Connection\Result\MySqlResultColumnTypeResolver::class => 'ZtdQuery\\Platform\\MySql\\MySqlResultColumnTypeResolver',
    ZtdQuery\Platform\MySql\Rewrite\Cte\MySqlCteShadowComposer::class => 'ZtdQuery\\Platform\\MySql\\MySqlCteShadowComposer',
    ZtdQuery\Platform\MySql\Rewrite\FullText\MySqlFullTextSearchRewriter::class => 'ZtdQuery\\Platform\\MySql\\MySqlFullTextSearchRewriter',
    ZtdQuery\Platform\MySql\Rewrite\GeneratedColumn\MySqlGeneratedColumnProjector::class => 'ZtdQuery\\Platform\\MySql\\MySqlGeneratedColumnProjector',
    ZtdQuery\Platform\MySql\Rewrite\LoadData\MySqlLoadDataProjector::class => 'ZtdQuery\\Platform\\MySql\\MySqlLoadDataProjector',
    ZtdQuery\Platform\MySql\Rewrite\MySqlQueryGuard::class => 'ZtdQuery\\Platform\\MySql\\MySqlQueryGuard',
    ZtdQuery\Platform\MySql\Rewrite\MySqlRewriter::class => 'ZtdQuery\\Platform\\MySql\\MySqlRewriter',
    ZtdQuery\Platform\MySql\Rewrite\Partition\MySqlPartitionSelectionRewriter::class => 'ZtdQuery\\Platform\\MySql\\MySqlPartitionSelectionRewriter',
    ZtdQuery\Platform\MySql\Rewrite\Transformer\DeleteTransformer::class => 'ZtdQuery\\Platform\\MySql\\Transformer\\DeleteTransformer',
    ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertRowRenderer::class => 'ZtdQuery\\Platform\\MySql\\Transformer\\InsertRowRenderer',
    ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertSelectRenderer::class => 'ZtdQuery\\Platform\\MySql\\Transformer\\InsertSelectRenderer',
    ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertTransformer::class => 'ZtdQuery\\Platform\\MySql\\Transformer\\InsertTransformer',
    ZtdQuery\Platform\MySql\Rewrite\Transformer\MySqlSelectListAliaser::class => 'ZtdQuery\\Platform\\MySql\\Transformer\\MySqlSelectListAliaser',
    ZtdQuery\Platform\MySql\Rewrite\Transformer\MySqlTransformer::class => 'ZtdQuery\\Platform\\MySql\\Transformer\\MySqlTransformer',
    ZtdQuery\Platform\MySql\Rewrite\Transformer\ReplaceTransformer::class => 'ZtdQuery\\Platform\\MySql\\Transformer\\ReplaceTransformer',
    ZtdQuery\Platform\MySql\Rewrite\Transformer\SelectTransformer::class => 'ZtdQuery\\Platform\\MySql\\Transformer\\SelectTransformer',
    ZtdQuery\Platform\MySql\Rewrite\Transformer\UpdateTransformer::class => 'ZtdQuery\\Platform\\MySql\\Transformer\\UpdateTransformer',
    ZtdQuery\Platform\MySql\Rewrite\Type\MySqlTypeSemantics::class => 'ZtdQuery\\Platform\\MySql\\MySqlTypeSemantics',
    ZtdQuery\Platform\MySql\Rewrite\Upsert\MySqlNativeUpsertProjector::class => 'ZtdQuery\\Platform\\MySql\\MySqlNativeUpsertProjector',
    ZtdQuery\Platform\MySql\Rewrite\View\MySqlViewShadowRenderer::class => 'ZtdQuery\\Platform\\MySql\\MySqlViewShadowRenderer',
    ZtdQuery\Platform\MySql\Schema\Key\MySqlForeignKeyDefinitionParser::class => 'ZtdQuery\\Platform\\MySql\\MySqlForeignKeyDefinitionParser',
    ZtdQuery\Platform\MySql\Schema\MySqlColumnTypeMapper::class => 'ZtdQuery\\Platform\\MySql\\MySqlColumnTypeMapper',
    ZtdQuery\Platform\MySql\Schema\MySqlSchemaParser::class => 'ZtdQuery\\Platform\\MySql\\MySqlSchemaParser',
    ZtdQuery\Platform\MySql\Schema\MySqlSchemaReflector::class => 'ZtdQuery\\Platform\\MySql\\MySqlSchemaReflector',
    ZtdQuery\Platform\MySql\Schema\Partition\MySqlPartitioningParser::class => 'ZtdQuery\\Platform\\MySql\\MySqlPartitioningParser',
    ZtdQuery\Platform\MySql\Schema\View\MySqlViewDefinitionParser::class => 'ZtdQuery\\Platform\\MySql\\MySqlViewDefinitionParser',
    ZtdQuery\Platform\MySql\Shadow\Mutation\Table\AlterTableMutation::class => 'ZtdQuery\\Platform\\MySql\\Mutation\\AlterTableMutation',
    ZtdQuery\Platform\MySql\Shadow\Mutation\Upsert\MySqlUpsertExpressionParser::class => 'ZtdQuery\\Platform\\MySql\\MySqlUpsertExpressionParser',
    ZtdQuery\Platform\MySql\Shadow\MySqlMutationResolver::class => 'ZtdQuery\\Platform\\MySql\\MySqlMutationResolver',
    ZtdQuery\Platform\MySql\Sql\Diagnostic\MySqlReadOnlyDiagnosticStatement::class => 'ZtdQuery\\Platform\\MySql\\MySqlReadOnlyDiagnosticStatement',
    ZtdQuery\Platform\MySql\Sql\Dml\DmlWhereClauseExtractor::class => 'ZtdQuery\\Platform\\MySql\\DmlWhereClauseExtractor',
    ZtdQuery\Platform\MySql\Sql\Dml\InsertSelectSourceExtractor::class => 'ZtdQuery\\Platform\\MySql\\InsertSelectSourceExtractor',
    ZtdQuery\Platform\MySql\Sql\Dml\UpdateAssignmentExtractor::class => 'ZtdQuery\\Platform\\MySql\\UpdateAssignmentExtractor',
    ZtdQuery\Platform\MySql\Sql\Dml\UpdateSourceExtractor::class => 'ZtdQuery\\Platform\\MySql\\UpdateSourceExtractor',
    ZtdQuery\Platform\MySql\Sql\MySqlIdentifierQuoter::class => 'ZtdQuery\\Platform\\MySql\\MySqlIdentifierQuoter',
    ZtdQuery\Platform\MySql\Sql\MySqlLexerProfile::class => 'ZtdQuery\\Platform\\MySql\\MySqlLexerProfile',
    ZtdQuery\Platform\MySql\Sql\MySqlParser::class => 'ZtdQuery\\Platform\\MySql\\MySqlParser',
    ZtdQuery\Platform\MySql\Sql\Relation\MySqlSelectRelationParser::class => 'ZtdQuery\\Platform\\MySql\\MySqlSelectRelationParser',
    ZtdQuery\Platform\MySql\Sql\Transaction\MySqlTransactionStatementParser::class => 'ZtdQuery\\Platform\\MySql\\MySqlTransactionStatementParser',
    ZtdQuery\Platform\MySql\Sql\Upsert\MySqlUpsertAssignmentExtractor::class => 'ZtdQuery\\Platform\\MySql\\MySqlUpsertAssignmentExtractor',
    ZtdQuery\Platform\MySql\Sql\Value\MySqlCastRenderer::class => 'ZtdQuery\\Platform\\MySql\\MySqlCastRenderer',
    ZtdQuery\Platform\MySql\Sql\Value\MySqlValueRenderer::class => 'ZtdQuery\\Platform\\MySql\\MySqlValueRenderer',
];
$currentNames = array_flip($formerNames);

spl_autoload_register(static function (string $class) use ($formerNames, $currentNames): void {
    $current = $currentNames[$class] ?? $class;
    $former = $formerNames[$current] ?? null;
    if ($former === null) {
        return;
    }
    if (!class_exists($current, false)) {
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            if ($loader->loadClass($current) === true) {
                break;
            }
        }
    }
    if (class_exists($current, false) && !class_exists($former, false)) {
        class_alias($current, $former);
    }
}, true, true);
