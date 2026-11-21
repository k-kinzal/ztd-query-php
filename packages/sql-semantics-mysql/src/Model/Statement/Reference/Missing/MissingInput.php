<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing;

/**
 * An input the analysis context does not contain and a fact therefore depends on.
 *
 * @visibility public
 * @example Naming the undeclared relation behind a dependent fact
 *     $operation = (new \SqlSemantics\Platform\MySql\Semantics())->inspect('SELECT a FROM t');
 *     $operation->field('a')->type->missing[0]->describe() // => 'the declaration of relation t'
 */
interface MissingInput
{
    /**
     * Describes the missing input for a person.
     */
    public function describe(): string;
}
