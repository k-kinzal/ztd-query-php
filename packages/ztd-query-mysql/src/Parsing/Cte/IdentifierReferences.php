<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Parsing\Cte;

use ZtdQuery\Sql\SqlLexerProfile;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Identifier References.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class IdentifierReferences
{
    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(private readonly SqlLexerProfile $lexerProfile)
    {
    }
    /**
     * References Identifier for the supplied MySQL input.
     */
    public function referencesIdentifier(string $sql, string $identifier): bool
    {
        foreach (SqlTokenStream::tokenize($sql, $this->lexerProfile)->significantTokens() as $token) {
            $candidate = (new HeaderParser($this->lexerProfile))->identifierName($token);
            if ($candidate !== null && strcasecmp($candidate, $identifier) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $identifiers
     */
    public function referencesAnyIdentifier(string $sql, array $identifiers): bool
    {
        foreach ($identifiers as $identifier) {
            if ($this->referencesIdentifier($sql, $identifier)) {
                return true;
            }
        }

        return false;
    }
}
