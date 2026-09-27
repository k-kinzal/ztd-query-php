<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

/**
 * A structured SQL value that writes its own fields and fixed syntax.
 *
 * A value knows the values it is made of, in SQL order, and can rebuild
 * itself around replacements for them, so any statement can be walked and
 * rewritten without knowing its concrete classes. Lexical fields such as a
 * name or a literal spelling, and comments, are not child values.
 *
 * @visibility public
 * @example Accepting a structured SQL value
 *     $write = static fn (\SqlSemantics\Statement\Element $value): string => \SqlSemantics\Statement\Writer::render($value);
 *     $write instanceof \Closure // => true
 * @example Reading the operands of a value
 *     $value = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1')->command;
 *     count($value->children()) // => 1
 */
interface Element
{
    /**
     * Writes SQL using this value's data, without a parser or a source tree.
     */
    public function write(Writer $writer): void;

    /**
     * Lists the values this value is made of, in the order they are written.
     *
     * @return list<Element>
     */
    public function children(): array;

    /**
     * Returns this value rebuilt around what the function answers for each child, keeping every other field.
     *
     * A value returns itself when the function answers every child with the
     * child itself, so a value keeps its identity until something below it
     * is replaced. The replacement for a child must be a value the child's
     * position accepts.
     *
     * @param callable(Element): Element $replace
     */
    public function map(callable $replace): static;
}
