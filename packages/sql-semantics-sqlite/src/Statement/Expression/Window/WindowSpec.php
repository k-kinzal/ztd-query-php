<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Window;

use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;

/**
 * A window specification: an optional base window, a partition, an ordering and a frame.
 *
 * Source: https://sqlite.org/windowfunctions.html#window_chaining.
 *
 * @visibility SqlSemantics
 */
final class WindowSpec implements Node
{
    use Snapshot;

    /**
     * @var list<Scalar> The partitioning expressions
     */
    public readonly array $partition;

    /**
     * @var list<SortTerm> The ordering terms
     */
    public readonly array $order;

    /**
     * @param Name|null $base The window this one extends
     * @param list<Scalar> $partition The partitioning expressions
     * @param list<SortTerm> $order The ordering terms
     * @param Frame|null $frame The frame
     */
    public function __construct(public readonly ?Name $base = null, array $partition = [], array $order = [], public readonly ?Frame $frame = null)
    {
        $this->partition = Check::listOf($partition, Scalar::class, 'A window partition is a list of expressions.');
        $this->order = Check::listOf($order, SortTerm::class, 'A window ordering is a list of ordering terms.');
    }

    /**
     * Answers every expression of the window in written order.
     *
     * @return list<Scalar>
     */
    public function expressions(): array
    {
        $expressions = $this->partition;
        foreach ($this->order as $term) {
            $expressions[] = $term->expression;
        }
        foreach ([$this->frame?->start->offset, $this->frame?->end?->offset] as $offset) {
            if ($offset !== null) {
                $expressions[] = $offset;
            }
        }

        return $expressions;
    }

    /**
     * Writes the specification without its parentheses.
     */
    public function render(Output $out): void
    {
        if ($this->base !== null) {
            $out->name($this->base, NameUse::Label);
        }
        if ($this->partition !== []) {
            $out->keyword('PARTITION', 'BY')->list($this->partition);
        }
        if ($this->order !== []) {
            $out->keyword('ORDER', 'BY')->list($this->order);
        }
        $out->node($this->frame);
    }
}
