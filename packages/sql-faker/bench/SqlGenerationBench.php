<?php

declare(strict_types=1);

namespace Bench;

use Faker\Factory;
use PhpBench\Attributes as Bench;
use SqlFaker\MySqlProvider;
use SqlFaker\PostgreSqlProvider;
use SqlFaker\SqliteProvider;

#[Bench\Groups(['generation'])]
final class SqlGenerationBench
{
    private MySqlProvider $mySqlProvider;
    private PostgreSqlProvider $postgreSqlProvider;
    private SqliteProvider $sqliteProvider;

    public function setUpMySql(): void
    {
        $mySqlFaker = Factory::create();
        $mySqlFaker->seed(2001);
        $this->mySqlProvider = new MySqlProvider($mySqlFaker, 'mysql-8.4.7');
    }

    public function setUpPostgreSql(): void
    {
        $postgresFaker = Factory::create();
        $postgresFaker->seed(2002);
        $this->postgreSqlProvider = new PostgreSqlProvider($postgresFaker, 'pg-17.2');
    }

    public function setUpSqlite(): void
    {
        $sqliteFaker = Factory::create();
        $sqliteFaker->seed(2003);
        $this->sqliteProvider = new SqliteProvider($sqliteFaker, 'sqlite-3.47.2');
    }

    #[Bench\BeforeMethods('setUpMySql')]
    #[Bench\Revs(250)]
    public function benchGenerateMySqlSelect(): void
    {
        $this->mySqlProvider->selectStatement(maxDepth: 6);
    }

    #[Bench\BeforeMethods('setUpPostgreSql')]
    #[Bench\Revs(250)]
    public function benchGeneratePostgreSqlSelect(): void
    {
        $this->postgreSqlProvider->selectStatement(maxDepth: 6);
    }

    #[Bench\BeforeMethods('setUpSqlite')]
    #[Bench\Revs(250)]
    public function benchGenerateSqliteSelect(): void
    {
        $this->sqliteProvider->selectStatement(maxDepth: 6);
    }
}
