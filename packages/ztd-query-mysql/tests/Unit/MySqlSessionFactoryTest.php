<?php

declare(strict_types=1);

namespace Tests\Unit;

use PhpMyAdmin\SqlParser\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Platform\MySql\MySqlLexerProfile;
use ZtdQuery\Platform\MySql\MySqlMutationResolver;
use ZtdQuery\Platform\MySql\MySqlCastRenderer;
use ZtdQuery\Platform\MySql\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\MySqlResultColumnTypeResolver;
use ZtdQuery\Platform\MySql\MySqlQueryGuard;
use ZtdQuery\Platform\MySql\MySqlRewriter;
use ZtdQuery\Platform\MySql\MySqlSchemaParser;
use ZtdQuery\Platform\MySql\MySqlSchemaReflector;
use ZtdQuery\Platform\MySql\MySqlSessionFactory;
use ZtdQuery\Platform\MySql\MySqlSessionSqlModeReflector;
use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\InsertTransformer;
use ZtdQuery\Platform\MySql\Transformer\MySqlTransformer;
use ZtdQuery\Platform\MySql\Transformer\ReplaceTransformer;
use ZtdQuery\Platform\MySql\Transformer\SelectTransformer;
use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use ZtdQuery\Session;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(MySqlSessionFactory::class)]
#[UsesClass(MySqlLexerProfile::class)]
#[UsesClass(MySqlMutationResolver::class)]
#[UsesClass(MySqlCastRenderer::class)]
#[UsesClass(MySqlIdentifierQuoter::class)]
#[UsesClass(MySqlParser::class)]
#[UsesClass(MySqlResultColumnTypeResolver::class)]
#[UsesClass(MySqlQueryGuard::class)]
#[UsesClass(MySqlRewriter::class)]
#[UsesClass(MySqlSchemaParser::class)]
#[UsesClass(MySqlSchemaReflector::class)]
#[UsesClass(MySqlSessionSqlModeReflector::class)]
#[UsesClass(DeleteTransformer::class)]
#[UsesClass(InsertTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\InsertRowRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\InsertSelectRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\MySqlSelectListAliaser::class)]
#[UsesClass(MySqlTransformer::class)]
#[UsesClass(ReplaceTransformer::class)]
#[UsesClass(SelectTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlFullTextSearchRewriter::class)]
#[UsesClass(UpdateTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlValueRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlTypeSemantics::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlCteShadowComposer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlNativeUpsertProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlGeneratedColumnProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlReadOnlyDiagnosticStatement::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlSelectRelationParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlViewDefinitionParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlViewShadowRenderer::class)]
final class MySqlSessionFactoryTest extends TestCase
{
    public function testCreateRegistersReflectedViews(): void
    {
        $empty = self::createStub(StatementInterface::class);
        $empty->method('fetchAll')->willReturn([]);
        $views = self::createStub(StatementInterface::class);
        $views->method('fetchAll')->willReturn([['name' => 'active_users']]);
        $create = self::createStub(StatementInterface::class);
        $create->method('fetchAll')->willReturn([
            ['Create View' => 'CREATE VIEW active_users AS SELECT 1 AS id'],
        ]);
        $connection = self::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturnCallback(
            static fn (string $sql): StatementInterface => match ($sql) {
                'SHOW TABLES' => $empty,
                "SHOW FULL TABLES WHERE Table_type = 'VIEW'" => $views,
                'SHOW CREATE VIEW `active_users`' => $create,
                default => $empty,
            },
        );

        $session = (new MySqlSessionFactory())->create($connection, ZtdConfig::default());

        self::assertSame(
            "WITH `active_users` AS (SELECT 1 AS id)\nSELECT * FROM active_users",
            $session->rewrite('SELECT * FROM active_users')->sql(),
        );
    }

    public function testCreateReturnsSession(): void
    {
        $statement = self::createStub(StatementInterface::class);
        $statement->method('fetchAll')->willReturn([]);

        $connection = self::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturn($statement);

        $config = new ZtdConfig();
        $factory = new MySqlSessionFactory();
        $session = $factory->create($connection, $config);

        self::assertInstanceOf(Session::class, $session);
        self::assertInstanceOf(MySqlResultColumnTypeResolver::class, $session->resultColumnTypeResolver());
    }

    public function testCreateAppliesReflectedAnsiQuotesModeToProductionLexing(): void
    {
        $empty = self::createStub(StatementInterface::class);
        $empty->method('fetchAll')->willReturn([]);
        $sqlMode = self::createStub(StatementInterface::class);
        $sqlMode->method('fetchAll')->willReturn([['ztd_sql_mode' => 'STRICT_TRANS_TABLES,ANSI_QUOTES']]);
        $connection = self::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturnCallback(
            static fn (string $sql): StatementInterface => $sql === 'SELECT @@SESSION.sql_mode AS ztd_sql_mode'
                ? $sqlMode
                : $empty,
        );
        $previousMode = Context::getMode();

        try {
            (new MySqlSessionFactory())->create($connection, ZtdConfig::default());
            $tokens = SqlTokenStream::tokenize('"column"', MySqlLexerProfile::create())->significantTokens();

            self::assertSame(SqlTokenKind::QuotedIdentifier, $tokens[0]->kind);
        } finally {
            Context::setMode($previousMode);
        }
    }
}
