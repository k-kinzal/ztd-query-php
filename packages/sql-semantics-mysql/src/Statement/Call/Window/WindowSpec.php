<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Window;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A parenthesized window specification: an optional window it refines, a partitioning, an ordering and a frame.
 *
 * Rule: MYSQL-WINDOW-SPEC-001. The expressions are derived at the position
 * of the window. The base window is a window of the WINDOW clause, which
 * the query family resolves. A GROUPS frame and an EXCLUDE clause are
 * accepted by the grammar and rejected by the server (ER_NOT_SUPPORTED_YET).
 * Terminates: the parts are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-functions-usage.html,
 * https://dev.mysql.com/doc/refman/8.4/en/window-functions-named-windows.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Holding the parts of a window
 *     $order = [new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('b')))];
 *     $window = new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec(null, [], $order, new \SqlSemantics\Platform\MySql\Statement\Call\Window\Frame(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit::Rows, new \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::UnboundedPreceding)));
 *     [count($window->partition), count($window->order), $window->frame?->unit] // => [0, 1, \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit::Rows]
 */
final class WindowSpec implements WindowSpecification
{
    use Snapshot;

    /**
     * @var list<OrderItem> The partitioning expressions
     */
    public readonly array $partition;

    /**
     * @var list<OrderItem> The ordering items
     */
    public readonly array $order;

    /**
     * @param Name|null $base The window this one refines
     * @param list<OrderItem> $partition The partitioning expressions
     * @param list<OrderItem> $order The ordering items
     * @param Frame|null $frame The frame
     */
    public function __construct(public readonly ?Name $base = null, array $partition = [], array $order = [], public readonly ?Frame $frame = null)
    {
        $this->partition = Check::listOf($partition, OrderItem::class, 'A window partition is a list of ordering items.');
        $this->order = Check::listOf($order, OrderItem::class, 'A window ordering is a list of ordering items.');
    }

    /**
     * Derives the partitioning, the ordering and the frame offsets, and reports what the server does not support.
     */
    public function deriveWindow(Derivation $derivation, Environment $environment): void
    {
        foreach ([...$this->partition, ...$this->order] as $item) {
            (new Arguments())->one($item->expression, $derivation, $environment);
        }
        foreach ($this->frame?->offsets() ?? [] as $offset) {
            (new Arguments())->one($offset, $derivation, $environment);
        }
        if ($this->frame?->unit === FrameUnit::Groups) {
            $derivation->report(new UnsupportedWindowing(WindowingLimit::GroupsUnit));
        }
        if ($this->frame?->exclusion !== null) {
            $derivation->report(new UnsupportedWindowing(WindowingLimit::Exclusion));
        }
    }

    /**
     * Writes the parenthesized specification.
     */
    public function render(Output $out): void
    {
        $out->symbol('(');
        if ($this->base !== null) {
            $out->name($this->base, NameUse::Label);
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
