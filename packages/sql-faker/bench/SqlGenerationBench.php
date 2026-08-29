<?php

declare(strict_types=1);

namespace Bench;

use Faker\Factory;
use SqlFaker\MySqlStatementProvider;
use SqlFaker\PostgreSqlStatementProvider;
use SqlFaker\SqliteProvider;

final class SqlGenerationBench
{
    private MySqlStatementProvider $mySqlProvider;
    private PostgreSqlStatementProvider $postgreSqlProvider;
    private SqliteProvider $sqliteProvider;

    public function setUp(): void
    {
        $mySqlFaker = Factory::create();
        $mySqlFaker->seed(2001);
        $this->mySqlProvider = new MySqlStatementProvider($mySqlFaker);

        $postgresFaker = Factory::create();
        $postgresFaker->seed(2002);
        $this->postgreSqlProvider = new PostgreSqlStatementProvider($postgresFaker);

        $sqliteFaker = Factory::create();
        $sqliteFaker->seed(2003);
        $this->sqliteProvider = new SqliteProvider($sqliteFaker);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(50)
     * @Iterations(5)
     */
    public function benchGenerateMySqlSelect(): void
    {
        $this->mySqlProvider->selectStatement(maxDepth: 6);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(50)
     * @Iterations(5)
     */
    public function benchGeneratePostgreSqlSelect(): void
    {
        $this->postgreSqlProvider->selectStatement(maxDepth: 6);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(50)
     * @Iterations(5)
     */
    public function benchGenerateSqliteSelect(): void
    {
        $this->sqliteProvider->selectStatement(maxDepth: 6);
    }
}
