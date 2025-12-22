<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\ReplaceStatement;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use PhpMyAdmin\SqlParser\Statements\WithStatement;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Rewrite\SqlTransformer;

/**
 * Composite SQL transformer for MySQL.
 *
 * Parses the SQL, determines its type, and delegates to the appropriate
 * sub-transformer. DDL and unsupported statements throw UnsupportedSqlException.
 */
final class MySqlTransformer implements SqlTransformer
{
    private MySqlParser $parser;
    private SelectTransformer $selectTransformer;
    private InsertTransformer $insertTransformer;
    private UpdateTransformer $updateTransformer;
    private DeleteTransformer $deleteTransformer;
    private ReplaceTransformer $replaceTransformer;

    public function __construct(
        MySqlParser $parser,
        SelectTransformer $selectTransformer,
        InsertTransformer $insertTransformer,
        UpdateTransformer $updateTransformer,
        DeleteTransformer $deleteTransformer,
        ReplaceTransformer $replaceTransformer
    ) {
        $this->parser = $parser;
        $this->selectTransformer = $selectTransformer;
        $this->insertTransformer = $insertTransformer;
        $this->updateTransformer = $updateTransformer;
        $this->deleteTransformer = $deleteTransformer;
        $this->replaceTransformer = $replaceTransformer;
    }

    /**
     * {@inheritDoc}
     */
    public function transform(string $sql, array $tables): string
    {
        $statements = $this->parser->parse($sql);
        if ($statements === []) {
            throw new UnsupportedSqlException($sql, 'Empty or unparseable');
        }

        $statement = $statements[0];

        if ($statement instanceof SelectStatement || $statement instanceof WithStatement) {
            return $this->selectTransformer->transform($sql, $tables);
        }

        if ($statement instanceof InsertStatement) {
            return $this->insertTransformer->transform($sql, $tables);
        }

        if ($statement instanceof UpdateStatement) {
            return $this->updateTransformer->transform($sql, $tables);
        }

        if ($statement instanceof DeleteStatement) {
            return $this->deleteTransformer->transform($sql, $tables);
        }

        if ($statement instanceof ReplaceStatement) {
            return $this->replaceTransformer->transform($sql, $tables);
        }

        throw new UnsupportedSqlException($sql, 'Statement type not supported by transformer');
    }
}
