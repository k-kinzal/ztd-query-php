<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

use ZtdQuery\Sql\SqlTokenDialect;
use ZtdQuery\Sql\SqlTokenStream;

final class ViewDefinition
{
    /** @param list<string> $dependencies */
    public function __construct(
        public readonly string $query,
        public readonly array $dependencies,
        private readonly SqlTokenDialect $dialect = SqlTokenDialect::Standard,
    ) {
    }

    public static function fromQuery(
        string $query,
        SqlTokenDialect $dialect = SqlTokenDialect::Standard,
    ): self {
        $query = rtrim(trim($query), ';');

        return new self(
            $query,
            SqlTokenStream::tokenize($query, $dialect)->selectTableNames(),
            $dialect,
        );
    }

    public static function fromCreateStatement(
        string $sql,
        SqlTokenDialect $dialect = SqlTokenDialect::Standard,
    ): ?self {
        foreach (SqlTokenStream::tokenize($sql, $dialect)->significantTokens() as $token) {
            if (!$token->isTopLevel() || !$token->isKeyword('AS')) {
                continue;
            }
            $query = substr($sql, $token->endOffset());
            if (trim($query) !== '') {
                return self::fromQuery($query, $dialect);
            }
        }

        return null;
    }

    /** @param list<string> $relationNames */
    public function shadowQuery(array $relationNames): string
    {
        return SqlTokenStream::tokenize($this->query, $this->dialect)
            ->unqualifySelectTableReferences($relationNames);
    }
}
