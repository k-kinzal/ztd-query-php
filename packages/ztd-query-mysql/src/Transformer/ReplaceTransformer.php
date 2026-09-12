<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use PhpMyAdmin\SqlParser\Statements\ReplaceStatement;
use RuntimeException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Rewrite\SqlTransformer;

/**
 * Transforms REPLACE statements into SELECT queries that return the replaced rows.
 * Applies CTE shadowing via the SelectTransformer delegate.
 */
final class ReplaceTransformer implements SqlTransformer
{
    private MySqlParser $parser;
    private InsertTransformer $insertTransformer;

    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(MySqlParser $parser, SelectTransformer $selectTransformer)
    {
        $this->parser = $parser;
        $this->insertTransformer = new InsertTransformer($parser, $selectTransformer);
    }

    /**
     * {@inheritDoc}
     * @throws UnsupportedSqlException
     */
    public function transform(string $sql, array $tables): string
    {
        $insertSql = (new Insert\ReplaceStatementConverter())->asInsert($sql);
        $statements = $this->parser->parse($sql);
        $statement = $statements[0] ?? null;
        if ($statement instanceof ReplaceStatement) {
            foreach ($statement->values ?? [] as $valueSet) {
                if ((get_object_vars($valueSet)['values'] ?? null) === []) {
                    throw new UnsupportedSqlException($sql, 'Invalid REPLACE statement');
                }
            }
        }

        try {
            return $this->insertTransformer->transform($insertSql, $tables);
        } catch (RuntimeException $exception) {
            throw new UnsupportedSqlException($sql, $exception->getMessage());
        }
    }

    /**
     * Commit Rewrite State for the supplied MySQL input.
     */
    public function commitRewriteState(): void
    {
        $this->insertTransformer->commitRewriteState();
    }

}
