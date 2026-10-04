<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Publication;

/**
 * What ALTER PUBLICATION or ALTER SUBSCRIPTION does with the listed objects: add them, replace the list with them, or remove them.
 *
 * Source: https://www.postgresql.org/docs/17/sql-alterpublication.html, https://www.postgresql.org/docs/17/sql-altersubscription.html.
 *
 * @visibility public
 * @example Spelling the replacing action
 *     \SqlSemantics\Platform\PostgreSql\Statement\Publication\PublicationAction::Set->value // => 'SET'
 */
enum PublicationAction: string
{
    case Add = 'ADD';
    case Set = 'SET';
    case Drop = 'DROP';
}
