<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Replication\SourceSettings;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A numeric source option refused before constructing its semantic value.
 *
 * Only the option kind, numeric operand and byte position survive lowering.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html.
 *
 * @visibility public
 * @example Describing an invalid replication switch
 *     (new \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSourceNumber(\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::GtidOnly, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('2'), 40))->message() // => 'GTID_ONLY requires a value in its numeric domain.'
 */
final class RefusedSourceNumber implements Diagnostic
{
    use Snapshot;

    /**
     * @param SourceOptionKind $kind The source option
     * @param Numeral $number The refused operand
     * @param int $offset The first byte of the numeric input operand
     */
    public function __construct(public readonly SourceOptionKind $kind, public readonly Numeral $number, public readonly int $offset)
    {
        Check::input(in_array($kind, [SourceOptionKind::ConnectionAutoFailover, SourceOptionKind::GtidOnly, SourceOptionKind::RequireRowFormat], true), 'A refused source switch names a Boolean replication option.');
        Check::input((new SourceSettings())->number($kind, $number) !== null, 'A refused source switch is outside its numeric domain.');
        Check::input($offset >= 0, 'A source operand begins at a nonnegative byte offset.');
    }

    /**
     * Describes the operand domain without retaining input SQL.
     */
    public function message(): string
    {
        return $this->kind->value . ' requires a value in its numeric domain.';
    }
}
