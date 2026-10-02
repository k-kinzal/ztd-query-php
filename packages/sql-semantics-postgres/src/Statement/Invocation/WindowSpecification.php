<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortItem;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A window specification in parentheses: an existing window to refine, a partition, an ordering and a frame.
 *
 * Mirrors PostgreSQL's `WindowDef` node. It is written after OVER and in the
 * WINDOW clause; the holder derives it in the environment of the query level
 * it belongs to.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SYNTAX-WINDOW-FUNCTIONS,
 * https://www.postgresql.org/docs/17/sql-select.html#SQL-WINDOW.
 *
 * @visibility public
 * @example Reading an empty window specification
 *     $window = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\WindowSpecification();
 *     [$window->existing, $window->partition, $window->order, $window->frame] // => [null, [], [], null]
 */
final class WindowSpecification implements Clause
{
    use Snapshot;

    /**
     * @var list<Scalar> The partitioning expressions
     */
    public readonly array $partition;

    /**
     * @var list<SortItem> The ordering within a partition
     */
    public readonly array $order;

    /**
     * @param Name|null $existing The window of the WINDOW clause this specification refines
     * @param list<Scalar> $partition The partitioning expressions
     * @param list<SortItem> $order The ordering within a partition
     * @param WindowFrame|null $frame The frame
     */
    public function __construct(public readonly ?Name $existing = null, array $partition = [], array $order = [], public readonly ?WindowFrame $frame = null)
    {
        $this->partition = Check::listOf($partition, Scalar::class, 'Partitioning expressions are expressions.');
        $this->order = Check::listOf($order, SortItem::class, 'A window ordering is a list of sort items.');
    }

    /**
     * Derives the partitioning expressions, the ordering and the frame offsets.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        foreach ($this->partition as $expression) {
            $derivation->scalar($expression, $environment);
        }
        foreach ($this->order as $item) {
            $item->deriveClause($derivation, $environment);
        }
        $this->frame?->deriveClause($derivation, $environment);
    }

    /**
     * Writes the specification in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(');
        if ($this->existing !== null) {
            $out->name($this->existing, NameUse::Column);
        }
        if ($this->partition !== []) {
            $out->keyword('PARTITION', 'BY')->list($this->partition);
        }
        if ($this->order !== []) {
            $out->keyword('ORDER', 'BY')->list($this->order);
        }
        $out->node($this->frame)->symbol(')');
    }
}
