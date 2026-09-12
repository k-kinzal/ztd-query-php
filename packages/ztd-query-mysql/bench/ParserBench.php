<?php

declare(strict_types=1);

namespace Bench;

use PhpBench\Attributes as Bench;
use ZtdQuery\Platform\MySql\MySqlParser;

/**
 * Implements the Parser Bench contract for MySQL.
 */
final class ParserBench
{
    private MySqlParser $parser;

    private string $selectSql = 'SELECT u.id, u.name, o.status FROM users u JOIN orders o ON o.user_id = u.id WHERE u.id = 1';

    private string $insertSql = "INSERT INTO users (id, name, email) VALUES (1, 'Alice', 'alice@example.com')";

    /**
     * Set Up for the supplied MySQL input.
     */
    public function setUp(): void
    {
        $this->parser = new MySqlParser();
    }

    /**
     * Bench Parse Select for the supplied MySQL input.
     */
    #[Bench\BeforeMethods('setUp')]
    #[Bench\Revs(2000)]
    public function benchParseSelect(): void
    {
        $this->parser->parse($this->selectSql);
    }

    /**
     * Bench Parse Insert for the supplied MySQL input.
     */
    #[Bench\BeforeMethods('setUp')]
    #[Bench\Revs(2000)]
    public function benchParseInsert(): void
    {
        $this->parser->parse($this->insertSql);
    }
}
