<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Fact;

/**
 * A condition the database raises for a statement it still runs, such as the use of deprecated syntax.
 *
 * A warning does not make the statement fail; the problems that do are diagnostics. Warnings
 * are kept in the order the statement raises them while the database reads it.
 *
 * @visibility public
 * @example Reading the warnings of a statement
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT BINARY 1');
 *     $query->facts->warnings[0]->message() // => "'BINARY expr' is deprecated and will be removed in a future release. Please use CAST instead"
 */
interface Warning
{
    /**
     * Describes the condition for a person; the concrete class is the machine-readable kind.
     */
    public function message(): string;
}
