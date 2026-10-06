<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\TextSearch;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to map token types of a text search configuration to dictionaries: `ADD MAPPING` or `ALTER MAPPING FOR … WITH`.
 *
 * Rule: PG-TS-CONFIG-001. Mirrors `AlterTSConfigurationStmt` of kind
 * ADD_MAPPING or ALTER_MAPPING_FOR_TOKEN: token types and dictionaries in order.
 * Source: https://www.postgresql.org/docs/17/sql-altertsconfig.html. Status: Implemented.
 *
 * @visibility public
 * @example Adding a mapping
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TEXT SEARCH CONFIGURATION c ADD MAPPING FOR word, asciiword WITH simple');
 *     [count($operation->statement->tokens), $operation->toString()] // => [2, 'ALTER TEXT SEARCH CONFIGURATION c ADD MAPPING FOR word, asciiword WITH simple']
 */
final class MapTextSearchTokens implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<Name> The token types
     */
    public readonly array $tokens;

    /**
     * @var non-empty-list<DottedName> The dictionaries in the order they are consulted
     */
    public readonly array $dictionaries;

    /**
     * @param DottedName $configuration The configuration name
     * @param MappingChange $change Whether mappings are added or replaced
     * @param list<Name> $tokens The token types; at least one
     * @param list<DottedName> $dictionaries The dictionaries; at least one
     */
    public function __construct(public readonly DottedName $configuration, public readonly MappingChange $change, array $tokens, array $dictionaries)
    {
        $this->tokens = Check::listOf($tokens, Name::class, 'A mapping names at least one token type.', 1);
        $this->dictionaries = Check::listOf($dictionaries, DottedName::class, 'A mapping names at least one dictionary.', 1);
    }

    /**
     * Derives nothing: configurations and dictionaries are not part of a declaration context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'TEXT', 'SEARCH', 'CONFIGURATION')->node($this->configuration)->keyword($this->change->value, 'MAPPING', 'FOR');
        foreach ($this->tokens as $position => $token) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->name($token, NameUse::Column);
        }
        $out->keyword('WITH')->list($this->dictionaries);
    }
}
