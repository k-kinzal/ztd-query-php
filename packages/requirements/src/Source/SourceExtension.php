<?php

declare(strict_types=1);

namespace Requirements\Source;

use Requirements\Model\Source;

/**
 * Reads a source format and selects the units that coverage counts and evidence quotes.
 *
 * A unit has a stable location and its complete normalized text. The same method selects the
 * coverage scope, with the source's own selector, and the unit an evidence entry names, with
 * that entry's selector.
 *
 * @visibility public
 *
 * @example Selecting every line of an in-memory note
 *     $notes = new class () implements \Requirements\Source\SourceExtension { public function select(\Requirements\Model\Source $source, string $selector, string $directory, bool $live): array { return [new \Requirements\Source\Unit('note:1', 'Names start with a letter.')]; } };
 *     $notes->select(new \Requirements\Model\Source('notes', 'memory:notes', 'notes', '*'), '*', '/', false)[0]->text // => 'Names start with a letter.'
 */
interface SourceExtension
{
    /**
     * Selects source units.
     *
     * @param Source $source The source declaration
     * @param string $selector The scope selector or an evidence selector
     * @param string $directory The configuration directory that relative paths resolve against
     * @param bool $live Whether to read the current URI instead of a pinned snapshot
     *
     * @return list<Unit> The selected units
     */
    public function select(Source $source, string $selector, string $directory, bool $live): array;
}
