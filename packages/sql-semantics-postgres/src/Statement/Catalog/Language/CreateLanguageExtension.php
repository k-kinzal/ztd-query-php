<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * CREATE LANGUAGE without a handler, which the server performs as CREATE EXTENSION of the language name.
 *
 * Rule: PG-LANGUAGE-002. The grammar turns this form into a
 * `CreateExtensionStmt`: OR REPLACE becomes IF NOT EXISTS and TRUSTED is
 * ignored. The written keywords are kept so the request renders as written.
 * Source: https://www.postgresql.org/docs/17/sql-createlanguage.html ("CREATE LANGUAGE … without handler … is
 * equivalent to CREATE EXTENSION"). Status: Implemented.
 *
 * @visibility public
 * @example Installing a language by name
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE OR REPLACE LANGUAGE plperl');
 *     [$operation->statement->ifNotExists(), $operation->statement->name->value] // => [true, 'plperl']
 */
final class CreateLanguageExtension implements Statement
{
    use Snapshot;

    /**
     * @param bool $orReplace Whether OR REPLACE is written
     * @param bool $trusted Whether TRUSTED is written; the server ignores it
     * @param Name $name The language name, which is the extension name
     */
    public function __construct(public readonly bool $orReplace, public readonly bool $trusted, public readonly Name $name)
    {
    }

    /**
     * Tells whether the extension install is skipped when the extension exists, which OR REPLACE requests.
     */
    public function ifNotExists(): bool
    {
        return $this->orReplace;
    }

    /**
     * Derives nothing: an extension is not part of a declaration context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE');
        if ($this->orReplace) {
            $out->keyword('OR', 'REPLACE');
        }
        if ($this->trusted) {
            $out->keyword('TRUSTED');
        }
        $out->keyword('LANGUAGE')->name($this->name, NameUse::Column);
    }
}
