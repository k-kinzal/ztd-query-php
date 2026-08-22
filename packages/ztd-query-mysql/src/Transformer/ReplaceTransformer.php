<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use PhpMyAdmin\SqlParser\Statements\ReplaceStatement;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Platform\MySql\MySqlLexerProfile;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Transforms REPLACE statements into SELECT queries that return the replaced rows.
 * Applies CTE shadowing via the SelectTransformer delegate.
 */
final class ReplaceTransformer implements SqlTransformer
{
    private MySqlParser $parser;
    private InsertTransformer $insertTransformer;

    public function __construct(MySqlParser $parser, SelectTransformer $selectTransformer)
    {
        $this->parser = $parser;
        $this->insertTransformer = new InsertTransformer($parser, $selectTransformer);
    }

    /**
     * {@inheritDoc}
     */
    public function transform(string $sql, array $tables): string
    {
        $insertSql = $this->asInsert($sql);
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
        } catch (\RuntimeException $exception) {
            throw new UnsupportedSqlException($sql, $exception->getMessage());
        }
    }

    public function commitRewriteState(): void
    {
        $this->insertTransformer->commitRewriteState();
    }

    private function asInsert(string $sql): string
    {
        $tokens = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->significantTokens();
        if ($tokens === []) {
            throw new UnsupportedSqlException($sql, 'Expected REPLACE statement');
        }
        $token = $tokens[0];
        if (!$token->isKeyword('REPLACE')) {
            throw new UnsupportedSqlException($sql, 'Expected REPLACE statement');
        }

        return substr_replace($sql, 'INSERT', $token->offset, strlen($token->text));
    }
}
