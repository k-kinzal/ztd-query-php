<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Schema\ViewDefinition;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Implements the My Sql View Definition Parser contract for MySQL.
 */
final class MySqlViewDefinitionParser
{
    /**
     * From Query for the supplied MySQL input.
     */
    public function fromQuery(string $query): ViewDefinition
    {
        $query = rtrim(trim($query), ';');

        return new ViewDefinition($query, (new MySqlSelectRelationParser())->tableNames($query));
    }

    /**
     * From Create Statement for the supplied MySQL input.
     */
    public function fromCreateStatement(string $sql): ?ViewDefinition
    {
        foreach (SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->significantTokens() as $token) {
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
