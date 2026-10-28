<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Storage\Option;

use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;

/**
 * `NODEGROUP [=] id`: the NDB node group of a tablespace or log file group.
 *
 * The equals sign is optional and not written. NDB only.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html.
 *
 * @visibility public
 * @example Holding the option
 *     (new \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\NodegroupOption(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('2')))->group->text // => '2'
 * @example Refusing a fractional group number
 *     new \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\NodegroupOption(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('1.5')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class NodegroupOption implements StorageOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Numeral $group The node group identifier
     * @param bool $equals Whether the optional equals sign is written
     */
    public function __construct(public readonly Numeral $group, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly bool $equals = false)
    {
        \SqlSemantics\Diagnostic\Check::input((new \SqlSemantics\Platform\MySql\Rules\Server\Magnitudes())->integral($group), 'A node-group number is an integer token.');
    }

    /**
     * Answers the keyword of the option.
     */
    public function keyword(): string
    {
        return 'NODEGROUP';
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('NODEGROUP');
        if ($this->equals) {
            $out->symbol('=');
        }
        $out->node($this->group);
    }
}
