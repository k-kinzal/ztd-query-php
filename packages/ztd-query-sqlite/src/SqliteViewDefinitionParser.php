<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite;

use ZtdQuery\Schema\ViewDefinition;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * The sqlite view definition parser.
 */
final class SqliteViewDefinitionParser
{
    /**
     * Builds query.
     *
     * @param string $query
     * @return ViewDefinition
     */
    public function fromQuery(string $query): ViewDefinition
    {
        $query = rtrim(trim($query), ';');

        return new ViewDefinition($query, (new SqliteSelectRelationParser())->tableNames($query));
    }

    /**
     * Builds create statement.
     *
     * @param string $sql
     * @return ?ViewDefinition
     */
    public function fromCreateStatement(string $sql): ?ViewDefinition
    {
        foreach (SqlTokenStream::tokenize($sql, SqliteLexerProfile::create())->significantTokens() as $token) {
            if (!$token->isTopLevel() || !$token->isKeyword('AS')) {
                continue;
            }
            $query = substr($sql, $token->endOffset());
            if (trim($query) !== '') {
                return $this->fromQuery($query);
            }
        }

        return null;
    }
}
