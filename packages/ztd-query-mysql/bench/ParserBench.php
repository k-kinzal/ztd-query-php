<?php

declare(strict_types=1);

namespace Bench;

use ZtdQuery\Platform\MySql\MySqlParser;

final class ParserBench
{
    private MySqlParser $parser;

    private string $selectSql = 'SELECT u.id, u.name, o.status FROM users u JOIN orders o ON o.user_id = u.id WHERE u.id = 1';

    private string $insertSql = "INSERT INTO users (id, name, email) VALUES (1, 'Alice', 'alice@example.com')";

    public function setUp(): void
    {
        $this->parser = new MySqlParser();
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(100)
     * @Iterations(5)
     */
    public function benchParseSelect(): void
    {
        $this->parser->parse($this->selectSql);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(100)
     * @Iterations(5)
     */
    public function benchParseInsert(): void
    {
        $this->parser->parse($this->insertSql);
    }
}
