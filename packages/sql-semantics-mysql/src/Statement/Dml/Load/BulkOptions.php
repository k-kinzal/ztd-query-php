<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Load;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\ByteSize;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The trailing options of a bulk LOAD DATA: `PARALLEL = n`, `MEMORY = size` and `ALGORITHM = BULK`.
 *
 * They belong to the bulk load of the HeatWave grammars: ALGORITHM = BULK
 * since 8.0.30, PARALLEL and MEMORY since 8.2.
 * Source: https://dev.mysql.com/doc/heatwave-aws/en/mys-hw-aws-bulk-ingest.html.
 *
 * @visibility public
 * @example Reading the options of a bulk load
 *     $load = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("LOAD DATA INFILE 'f' INTO TABLE t PARALLEL = 4 ALGORITHM = BULK");
 *     [$load->statement->bulk->parallel->text, $load->statement->bulk->bulk] // => ['4', true]
 */
final class BulkOptions implements Node
{
    use Snapshot;

    /**
     * @param Numeral|null $parallel The number of threads
     * @param ByteSize|null $memory The memory the load may use
     * @param bool $bulk Whether ALGORITHM = BULK is written
     */
    public function __construct(public readonly ?Numeral $parallel = null, public readonly ?ByteSize $memory = null, public readonly bool $bulk = false)
    {
        Check::input($parallel !== null || $memory !== null || $bulk, 'Bulk options hold at least one option.');
    }

    /**
     * Writes the options.
     */
    public function render(Output $out): void
    {
        if ($this->parallel !== null) {
            $out->keyword('PARALLEL')->symbol('=')->node($this->parallel);
        }
        if ($this->memory !== null) {
            $out->keyword('MEMORY')->symbol('=')->node($this->memory);
        }
        if ($this->bulk) {
            $out->keyword('ALGORITHM')->symbol('=')->keyword('BULK');
        }
    }
}
