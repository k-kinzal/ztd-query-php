<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Reference\Missing;

/**
 * An input the analysis context does not contain and a fact therefore depends on.
 *
 * @visibility public
 * @example Naming the undeclared relation behind a dependent fact
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a FROM t');
 *     $operation->field('a')->type->missing[0]->describe() // => 'the declaration of relation t'
 */
interface MissingInput
{
    /**
     * Describes the missing input for a person.
     */
    public function describe(): string;
}
