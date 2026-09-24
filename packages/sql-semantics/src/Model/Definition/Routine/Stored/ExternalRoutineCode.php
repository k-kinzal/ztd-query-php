<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Stored;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A routine body written in a language other than SQL: the decoded source text the server passes to that language.
 * @visibility public
 * @example Reading a JavaScript routine body
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('CREATE FUNCTION f(a INT) RETURNS INT LANGUAGE JAVASCRIPT AS $$return a$$');
 *     $statement->body->language // => 'JAVASCRIPT'
 *     $statement->body->code // => 'return a'
 */
final class ExternalRoutineCode
{
    /**
     * Requires a named language other than SQL.
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $language, public readonly string $code)
    {
        if ($language === '' || strcasecmp($language, 'SQL') === 0) {
            throw new InvalidStructure('An external routine body requires a language other than SQL.');
        }
    }
}
