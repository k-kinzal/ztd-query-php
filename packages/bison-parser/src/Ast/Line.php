<?php

declare(strict_types=1);

namespace BisonParser\Ast;

use BisonParser\Ast\Declaration\Declaration;
use BisonParser\Ast\Rule\RhsItem;

/**
 * A `#line` directive: from here on, Bison counts lines from the number given, in the file named.
 *
 * Bison reads `#line` at the start of a line anywhere in the file and
 * changes the locations it reports and, through them, the order it
 * numbers symbols in. The directive is kept where it stands: among the
 * declarations, among the rules, or inside a rule.
 *
 * @visibility public
 *
 * @example Reading a line directive between two rules
 *     $file = (new \BisonParser\Parser())->parse("%%\na: ;\n#line 10 \"other.y\"\nb: ;\n");
 *     $line = $file->grammar[1];
 *     [$line->line, $line->file, (string) $line->location()] // => [10, 'other.y', '3:1']
 */
final class Line implements Declaration, RhsItem
{
    /**
     * @param int $line The line number the next line gets
     * @param string|null $file The file name given, or null when the directive names none
     * @param Location $location Where the directive is written
     */
    public function __construct(
        public readonly int $line,
        public readonly ?string $file,
        public readonly Location $location,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function location(): Location
    {
        return $this->location;
    }
}
