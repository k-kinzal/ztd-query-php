<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Platform\MySql\MySqlMutationResolver;
use ZtdQuery\Platform\MySql\MySqlQueryGuard;
use ZtdQuery\Platform\MySql\MySqlRewriter;
use ZtdQuery\Platform\MySql\MySqlSchemaParser;
use ZtdQuery\Platform\MySql\MySqlSchemaReflector;
use ZtdQuery\Platform\MySql\MySqlSessionFactory;
use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\InsertTransformer;
use ZtdQuery\Platform\MySql\Transformer\MySqlTransformer;
use ZtdQuery\Platform\MySql\Transformer\ReplaceTransformer;
use ZtdQuery\Platform\MySql\Transformer\SelectTransformer;
use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use ZtdQuery\Session;

#[CoversClass(MySqlSessionFactory::class)]
#[UsesClass(MySqlMutationResolver::class)]
#[UsesClass(MySqlQueryGuard::class)]
#[UsesClass(MySqlRewriter::class)]
#[UsesClass(MySqlSchemaParser::class)]
#[UsesClass(MySqlSchemaReflector::class)]
#[UsesClass(DeleteTransformer::class)]
#[UsesClass(InsertTransformer::class)]
#[UsesClass(MySqlTransformer::class)]
#[UsesClass(ReplaceTransformer::class)]
#[UsesClass(SelectTransformer::class)]
#[UsesClass(UpdateTransformer::class)]
final class MySqlSessionFactoryTest extends TestCase
{
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
    }
}
