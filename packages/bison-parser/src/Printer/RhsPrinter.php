<?php

declare(strict_types=1);

namespace BisonParser\Printer;

use BisonParser\Ast\Rule\Action;
use BisonParser\Ast\Rule\DprecItem;
use BisonParser\Ast\Rule\EmptyItem;
use BisonParser\Ast\Rule\ExpectItem;
use BisonParser\Ast\Rule\MergeItem;
use BisonParser\Ast\Rule\PrecItem;
use BisonParser\Ast\Rule\Predicate;
use BisonParser\Ast\Rule\RhsItem;
use BisonParser\Ast\Rule\SymbolItem;
use LogicException;

/**
 * Writes the items of a right-hand side the way Bison spells them.
 *
 * @visibility root
 */
final class RhsPrinter
{
    /**
     * @param SymbolPrinter $symbols Writes symbols and literals
     */
    public function __construct(private readonly SymbolPrinter $symbols = new SymbolPrinter())
    {
    }

    /**
     * Writes one item.
     *
     * @param RhsItem $item The item
     *
     * @return string The item text
     *
     * @throws LogicException When the item is of a class the printer does not know
     */
    public function print(RhsItem $item): string
    {
        return match ($item::class) {
            SymbolItem::class => $this->symbols->symbol($item->symbol) . $this->reference($item->namedReference),
            Action::class => ($item->tag === null ? '' : "<{$item->tag}>") . $this->symbols->code($item->code) . $this->reference($item->namedReference),
            Predicate::class => '%?' . $this->symbols->code($item->code),
            EmptyItem::class => '%empty',
            PrecItem::class => '%prec ' . $this->symbols->symbol($item->symbol),
            DprecItem::class => '%dprec ' . $item->value,
            MergeItem::class => "%merge <{$item->tag}>",
            ExpectItem::class => ($item->reduceReduce ? '%expect-rr ' : '%expect ') . $item->count,
            default => throw new LogicException('Unknown right-hand side item ' . $item::class),
        };
    }

    /**
     * Writes a `[name]` reference, or nothing when there is none.
     *
     * @param string|null $name The name
     *
     * @return string The bracketed name or an empty string
     */
    public function reference(?string $name): string
    {
        return $name === null ? '' : "[{$name}]";
    }
}
