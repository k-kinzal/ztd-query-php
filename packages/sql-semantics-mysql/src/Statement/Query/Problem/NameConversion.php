<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem;

use SqlSemantics\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Statement\Snapshot;

/**
 * The conversion MySQL applies to the text of an introduced string from the character set of its introducer to the system character set.
 *
 * Rule: MYSQL-SELECT-ITEM-NAME-001. The server names an unaliased string
 * literal after its value converted from the character set of the literal
 * to the system character set (utf8mb3), keeping at most 255 bytes of
 * whole characters. For an introducer whose characters are not ASCII
 * bytes (ucs2, utf16, utf16le, utf32), and for text with bytes outside
 * ASCII in a character set other than binary, utf8mb3 and utf8mb4, the
 * name depends on the server's conversion table of that character set,
 * which the profile does not carry. A text read in the client character
 * set depends on the session state `character_set_client` instead.
 * Source: sql/item.cc (`Name_string::copy`) and sql/thr_malloc.cc
 * (`sql_strmake_with_convert`) of each release,
 * https://dev.mysql.com/doc/refman/8.4/en/charset-introducer.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading why a column of a derived table cannot be decided by name
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT x FROM (SELECT _latin2'é') AS d", []);
 *     $query->field('x')->type->missing[0]->describe() // => 'the conversion MySQL applies to text in the character set latin2 when it names a column after it'
 */
final class NameConversion implements MissingInput
{
    use Snapshot;

    /**
     * @param string $charset The character set of the introducer, as written in lower case
     */
    public function __construct(public readonly string $charset)
    {
    }

    /**
     * Describes the missing input.
     */
    public function describe(): string
    {
        return 'the conversion MySQL applies to text in the character set ' . $this->charset . ' when it names a column after it';
    }
}
