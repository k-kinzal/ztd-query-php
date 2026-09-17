<?php

declare(strict_types=1);

namespace BisonParser\Ast\Rule;

use BisonParser\Ast\Location;

/**
 * A braced action on a right-hand side, with its optional type tag and `[name]` reference.
 *
 * Whether the action is a mid-rule action depends on what follows it in
 * the alternative; the tree records the position and leaves that reading
 * to the caller.
 *
 * @visibility public
 *
 * @example Reading a typed mid-rule action
 *     $file = (new \BisonParser\Parser())->parse("%%\ns: 'a' <int>{ \$\$ = 1; }[mid] 'b' ;\n");
 *     $action = $file->rules()[0]->alternatives[0]->items[1];
 *     $action->tag // => 'int'
 *     $action->code // => ' $$ = 1; '
 *     $action->namedReference // => 'mid'
 */
final class Action implements RhsItem
{
    /**
     * @param string|null $tag The type tag before the braces, or null when none is given
     * @param string $code The code without its braces
     * @param string|null $namedReference The `[name]` after the braces, or null when none is given
     * @param Location $location Where the tag or the opening brace is written
     */
    public function __construct(
        public readonly ?string $tag,
        public readonly string $code,
        public readonly ?string $namedReference,
        public readonly Location $location,
    ) {
    }

    /**
     * Answers where the item begins.
     *
     * @return Location Line and column of the tag or the opening brace
     */
    public function location(): Location
    {
        return $this->location;
    }
}
