<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\TextSearch;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;

/**
 * A request to remove the mappings of token types from a text search configuration.
 *
 * Rule: PG-TS-CONFIG-003. Mirrors `AlterTSConfigurationStmt` of kind
 * DROP_MAPPING with `missing_ok`.
 * Source: https://www.postgresql.org/docs/17/sql-altertsconfig.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class DropTextSearchMapping implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<Name> The token types
     */
    public readonly array $tokens;

    /**
     * @param DottedName $configuration The configuration name
     * @param bool $ifExists Whether IF EXISTS is written
     * @param list<Name> $tokens The token types; at least one
     */
    public function __construct(public readonly DottedName $configuration, public readonly bool $ifExists, array $tokens)
    {
        $this->tokens = Check::listOf($tokens, Name::class, 'A mapping names at least one token type.', 1);
    }

    /**
     * Derives nothing: configurations are not part of a declaration context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'TEXT', 'SEARCH', 'CONFIGURATION')->node($this->configuration)->keyword('DROP', 'MAPPING');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->keyword('FOR');
        foreach ($this->tokens as $position => $token) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->name($token, NameUse::Column);
        }
    }
}
