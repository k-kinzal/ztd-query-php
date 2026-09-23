<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Database;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Database\CurrentDatabase;
use SqlSemantics\Model\Statement\Definition\MySql as Statement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Binds database creation, default changes, and legacy directory-name upgrades separately.
 * @visibility SqlSemantics
 */
final class MySqlDatabases
{
    /**
     * Resolves an omitted database name from the supplied snapshot when available.
     * @throws UnclassifiedSql
     * @throws \SqlSemantics\InvalidSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        $tokens = $source->tokens();
        $operation = strtoupper($tokens[0]->text ?? '');
        if ($origin->dialect !== Dialect::MySql || !in_array($operation, ['CREATE', 'ALTER'], true) || ($tokens[1]->name ?? '') !== 'DATABASE') {
            return null;
        }
        $name = Tree::child($source, ['ident', 'ident_or_empty']);
        $target = $name === null ? ($context->tables->schema->defaultSchema === '' ? CurrentDatabase::Session : $context->tables->schema->defaultSchema) : $context->tables->identifiers->name($name->tokens()[0]);
        if ($target === '') {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::DatabaseName, $source);
        }
        $upgrade = array_filter($source->children, static fn ($child): bool => $child instanceof \SqlParser\Lexer\Token && $child->name === 'UPGRADE_SYM') !== [];
        if ($upgrade) {
            return new Statement\UpgradeDatabaseDirectoryStatement($origin, is_string($target) ? $target : throw new UnclassifiedSql('A directory upgrade requires a named database.'));
        }
        if ($operation === 'CREATE') {
            return new Statement\CreateDatabaseStatement($origin, is_string($target) ? $target : throw new UnclassifiedSql('Database creation requires a named database.'), DatabaseOptions::creation($source, $context->tables->identifiers), Tree::child($source, ['opt_if_not_exists']) !== null);
        }
        return new Statement\AlterDatabaseStatement($origin, $target, DatabaseOptions::alteration($source, $context->tables->identifiers));
    }
}
