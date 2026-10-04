<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Into;

use SqlSemantics\Platform\MySql\Statement\Dml\FileFormat;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * `INTO OUTFILE 'file'` with its character set and field and line format.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select-into.html.
 *
 * @visibility public
 * @example Telling the destination kind
 *     in_array(\SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDestination::class, class_implements(\SqlSemantics\Platform\MySql\Statement\Query\Into\IntoOutfile::class), true) // => true
 */
final class IntoOutfile implements IntoDestination
{
    use Snapshot;

    /**
     * @param Text $file The file name
     * @param FileFormat $format The character set and the field and line format
     */
    public function __construct(public readonly Text $file, public readonly FileFormat $format)
    {
    }

    /**
     * Writes the destination.
     */
    public function render(Output $out): void
    {
        $out->keyword('OUTFILE')->node($this->file)->node($this->format);
    }
}
