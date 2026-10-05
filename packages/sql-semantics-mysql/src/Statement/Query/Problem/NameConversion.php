<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem;

use SqlSemantics\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Statement\Snapshot;

/**
 * The character set conversion MySQL applies to the text it names an unaliased select list expression after.
 *
 * Rule: MYSQL-SELECT-ITEM-NAME-001. The server converts the text a column is
 * named after from the character set it is read in to the system character
 * set and keeps at most 255 bytes of the result (256 when no conversion is
 * needed). For a text with characters outside ASCII, or longer than 255
 * bytes, the name therefore depends on the session state
 * `character_set_client`, or for an introduced string on the server's
 * conversion table of its character set, which the profile does not carry.
 * Such a column has no fixed name, and a lookup that could only match it
 * depends on this input. Source: sql/item.cc (`Name_string::copy`) and
 * sql/thr_malloc.cc (`sql_strmake_with_convert`) of each release,
 * https://dev.mysql.com/doc/refman/8.4/en/charset-connection.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading why a column of a derived table cannot be decided by name
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT x FROM (SELECT 'é') AS d", []);
 *     $query->field('x')->type->missing[0]->describe() // => 'the character set conversion MySQL applies to the text it names a column after: character_set_client, or the character set of an introducer'
 */
final class NameConversion implements MissingInput
{
    use Snapshot;

    /**
     * Describes the missing input.
     */
    public function describe(): string
    {
        return 'the character set conversion MySQL applies to the text it names a column after: character_set_client, or the character set of an introducer';
    }
}
