<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\TextSearch;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ClauseFacts;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to change the template options of a text search dictionary.
 *
 * Rule: PG-TS-DICTIONARY-001. Mirrors `AlterTSDictionaryStmt`; an option
 * without a value is removed. Source: https://www.postgresql.org/docs/17/sql-altertsdictionary.html. Status: Implemented.
 *
 * @visibility public
 * @example Changing a dictionary option
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TEXT SEARCH DICTIONARY my_dict (StopWords = newrussian)');
 *     $operation->statement->options[0]->name->value // => 'stopwords'
 */
final class AlterTextSearchDictionary implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<Definition> The options
     */
    public readonly array $options;

    /**
     * @param DottedName $name The dictionary name
     * @param list<Definition> $options The options; at least one
     */
    public function __construct(public readonly DottedName $name, array $options)
    {
        $this->options = Check::listOf($options, Definition::class, 'A dictionary change has at least one option.', 1);
    }

    /**
     * Derives the option values.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ClauseFacts())->derive($derivation, $this->options);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'TEXT', 'SEARCH', 'DICTIONARY')->node($this->name)->symbol('(')->list($this->options)->symbol(')');
    }
}
