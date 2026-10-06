<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language;

/**
 * The role of a support function named by a language or a foreign-data wrapper.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createlanguage.html, https://www.postgresql.org/docs/17/sql-createforeigndatawrapper.html.
 *
 * @visibility public
 * @example Spelling the validator role
 *     \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\FunctionRole::Validator->value // => 'VALIDATOR'
 */
enum FunctionRole: string
{
    case Handler = 'HANDLER';
    case Validator = 'VALIDATOR';

    /**
     * Answers the option name the server receives.
     */
    public function option(): string
    {
        return strtolower($this->value);
    }
}
