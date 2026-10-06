<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to define a procedural language with its handler functions.
 *
 * Rule: PG-LANGUAGE-001. Mirrors `CreatePLangStmt`: replace, trusted, the
 * language name, the call handler, the inline handler and the validator. The
 * PROCEDURAL noise word is not kept.
 * Source: https://www.postgresql.org/docs/17/sql-createlanguage.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the handler of a new language
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TRUSTED LANGUAGE plsample HANDLER plsample_call_handler NO VALIDATOR');
 *     [$operation->statement->handler->last()->value, $operation->toString()] // => ['plsample_call_handler', 'CREATE TRUSTED LANGUAGE plsample HANDLER plsample_call_handler NO VALIDATOR']
 */
final class CreateLanguage implements Statement
{
    use Snapshot;

    /**
     * @param bool $orReplace Whether OR REPLACE is written
     * @param bool $trusted Whether TRUSTED is written
     * @param Name $name The language name
     * @param DottedName $handler The call handler
     * @param DottedName|null $inline The inline handler, when INLINE is written
     * @param FunctionClause|null $validator The VALIDATOR or NO VALIDATOR clause, when written
     */
    public function __construct(
        public readonly bool $orReplace,
        public readonly bool $trusted,
        public readonly Name $name,
        public readonly DottedName $handler,
        public readonly ?DottedName $inline = null,
        public readonly ?FunctionClause $validator = null,
    ) {
        Check::input($validator === null || $validator->role === FunctionRole::Validator, 'A language names a validator in its validator clause.');
    }

    /**
     * Derives nothing: the handler functions are not part of a declaration context.
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
        $out->keyword('LANGUAGE')->name($this->name, NameUse::Column)->keyword('HANDLER')->node($this->handler);
        if ($this->inline !== null) {
            $out->keyword('INLINE')->node($this->inline);
        }
        $out->node($this->validator);
    }
}
