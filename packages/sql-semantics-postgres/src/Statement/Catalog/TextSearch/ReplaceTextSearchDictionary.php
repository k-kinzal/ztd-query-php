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
 * A request to substitute one dictionary for another in the mappings of a text search configuration.
 *
 * Rule: PG-TS-CONFIG-002. Mirrors `AlterTSConfigurationStmt` of kind
 * REPLACE_DICT (no token types: every mapping) or REPLACE_DICT_FOR_TOKEN.
 * Source: https://www.postgresql.org/docs/17/sql-altertsconfig.html. Status: Implemented.
 *
 * @visibility public
 * @example Replacing a dictionary everywhere
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TEXT SEARCH CONFIGURATION c ALTER MAPPING REPLACE english WITH swedish');
 *     [$operation->statement->tokens, $operation->statement->replacement->last()->value] // => [[], 'swedish']
 */
final class ReplaceTextSearchDictionary implements Statement
{
    use Snapshot;

    /**
     * @var list<Name> The token types whose mappings change; empty for every mapping
     */
    public readonly array $tokens;

    /**
     * @param DottedName $configuration The configuration name
     * @param list<Name> $tokens The token types whose mappings change; empty for every mapping
     * @param DottedName $replaced The dictionary replaced
     * @param DottedName $replacement The dictionary put in its place
     */
    public function __construct(public readonly DottedName $configuration, array $tokens, public readonly DottedName $replaced, public readonly DottedName $replacement)
    {
        $this->tokens = Check::listOf($tokens, Name::class, 'Token types are names.');
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
        $out->keyword('ALTER', 'TEXT', 'SEARCH', 'CONFIGURATION')->node($this->configuration)->keyword('ALTER', 'MAPPING');
        if ($this->tokens !== []) {
            $out->keyword('FOR');
            foreach ($this->tokens as $position => $token) {
                if ($position > 0) {
                    $out->symbol(',');
                }
                $out->name($token, NameUse::Column);
            }
        }
        $out->keyword('REPLACE')->node($this->replaced)->keyword('WITH')->node($this->replacement);
    }
}
