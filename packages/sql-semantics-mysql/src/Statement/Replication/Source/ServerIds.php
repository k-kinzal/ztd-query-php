<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Source;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * The parenthesized server identifiers of IGNORE_SERVER_IDS; an empty list clears the setting.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html#crs-opt-ignore_server_ids.
 *
 * @visibility public
 * @example Holding the identifiers in order
 *     (new \SqlSemantics\Platform\MySql\Statement\Replication\Source\ServerIds([new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('2')]))->ids[0]->text // => '2'
 */
final class ServerIds implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<Numeral> The server identifiers in written order
     */
    public readonly array $ids;

    /**
     * @param list<Numeral> $ids The server identifiers in written order; empty to clear the setting
     * @param bool $leadingComma Whether the first server identifier follows an empty list element
     */
    public function __construct(array $ids, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly bool $leadingComma = false)
    {
        $this->ids = Check::listOf($ids, Numeral::class, 'IGNORE_SERVER_IDS lists numbers.');
        Check::input(!$leadingComma || $this->ids !== [], 'A leading empty server-ID element precedes an identifier.');
    }

    /**
     * Writes the list in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(');
        if ($this->leadingComma) {
            $out->symbol(',');
        }
        $out->list($this->ids)->symbol(')');
    }
}
