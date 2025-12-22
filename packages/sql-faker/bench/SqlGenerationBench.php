<?php

declare(strict_types=1);

namespace Bench;

use Faker\Factory;
use SqlFaker\MySqlProvider;
use SqlFaker\PostgreSqlProvider;
use SqlFaker\SqliteProvider;

final class SqlGenerationBench
{
    private MySqlProvider $mySqlProvider;
    private PostgreSqlProvider $postgreSqlProvider;
    private SqliteProvider $sqliteProvider;

    public function setUp(): void
    {
        $mySqlFaker = Factory::create();
        $mySqlFaker->seed(2001);
        $this->mySqlProvider = new MySqlProvider($mySqlFaker);

        $postgresFaker = Factory::create();
        $postgresFaker->seed(2002);
        $this->postgreSqlProvider = new PostgreSqlProvider($postgresFaker);

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
