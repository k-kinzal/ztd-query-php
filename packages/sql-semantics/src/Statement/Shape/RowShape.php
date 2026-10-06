<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Shape;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Statement\Snapshot;

/**
 * The ordered output positions of a relation occurrence or query.
 *
 * A shape is open when missing inputs prevent listing every position. The
 * known positions are then still exact, but more may exist.
 *
 * @visibility public
 * @example Reading a complete shape
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE t (a INTEGER, b TEXT)');
 *     $operation = $semantics->analyze('SELECT * FROM t', [$table]);
 *     [$operation->shape()->complete(), count($operation->shape()->slots)] // => [true, 2]
 */
final class RowShape
{
    use Snapshot;

    /**
     * @var list<OutputSlot> The known positions in order
     */
    public readonly array $slots;

    /**
     * @var list<MissingInput> The inputs whose absence leaves the shape open
     */
    public readonly array $missing;

    /**
     * @param list<OutputSlot> $slots The known positions in order
     * @param list<MissingInput> $missing The inputs whose absence leaves the shape open
     */
    public function __construct(array $slots, array $missing = [])
    {
        $this->slots = Check::listOf($slots, OutputSlot::class, 'A row shape holds an ordered list of output slots.');
        $this->missing = Check::listOf($missing, MissingInput::class, 'An open row shape names missing inputs.');
    }

    /**
     * Tells whether every output position is listed.
     */
    public function complete(): bool
    {
        return $this->missing === [];
    }
}
