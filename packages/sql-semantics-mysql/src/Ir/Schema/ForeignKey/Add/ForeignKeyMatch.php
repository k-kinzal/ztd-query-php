<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add;

/**
 * The declared MATCH metadata supported by native InnoDB.
 *
 * Every choice admits a referencing row that contains a NULL in its key.
 * @visibility public
 * @example Reading the retained catalog option
 *     \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyMatch::Full->value // => 'FULL'
 */
enum ForeignKeyMatch: string
{
    case None = 'NONE';
    case Full = 'FULL';
    case Partial = 'PARTIAL';
}
