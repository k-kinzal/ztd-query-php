<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Load;

use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One option of the FIELDS clause of a text file format: `TERMINATED BY`, `[OPTIONALLY] ENCLOSED BY` or `ESCAPED BY` a string.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/load-data.html.
 *
 * @visibility public
 * @example Reading the field terminator of INTO OUTFILE
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT 1 INTO OUTFILE 'f' FIELDS TERMINATED BY ','");
 *     [$query->statement->into->format->fields[0]->kind, $query->statement->into->format->fields[0]->text->value] // => [\SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOptionKind::Terminated, ',']
 */
final class FieldOption implements Node
{
    use Snapshot;

    /**
     * @param FieldOptionKind $kind What the option sets
     * @param Text $text The string it sets
     */
    public function __construct(public readonly FieldOptionKind $kind, public readonly Text $text)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        if ($this->kind === FieldOptionKind::OptionallyEnclosed) {
            $out->keyword('OPTIONALLY')->keyword('ENCLOSED');
        } else {
            $out->keyword($this->kind->value);
        }
        $out->keyword('BY')->node($this->text);
    }
}
