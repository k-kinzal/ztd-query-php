<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Definition;

/**
 * An attribute name of a PostgreSQL definition list, with the argument form it takes.
 * @visibility public
 * @example Reading an attribute of an aggregate definition
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE AGGREGATE total(integer) (SFUNC = int4pl, STYPE = integer)');
 *     $statement->options[0]->attribute->spelling() // => 'SFUNC'
 */
interface DefinitionAttribute
{
    /**
     * The attribute name as written in SQL.
     */
    public function spelling(): string;

    /**
     * The argument form the attribute takes.
     */
    public function kind(): DefinitionKind;

    /**
     * Returns the canonical keyword a choice argument selects, or null when the text selects none.
     */
    public function choose(string $text): ?string;
}
