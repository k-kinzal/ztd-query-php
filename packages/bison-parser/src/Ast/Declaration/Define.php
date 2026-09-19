<?php

declare(strict_types=1);

namespace BisonParser\Ast\Declaration;

use BisonParser\Ast\Location;

/**
 * A `%define` directive: a variable and, optionally, its value.
 *
 * `%define api.pure` sets the variable without a value, which Bison reads
 * as the keyword `true`; the tree keeps the value absent so that the file
 * prints back as written.
 *
 * @visibility public
 *
 * @example Reading a definition
 *     $file = (new \BisonParser\Parser())->parse("%define api.prefix {my_}\n%%\ns: 'a';\n");
 *     $file->declarations[0]->variable // => 'api.prefix'
 *     $file->declarations[0]->value // => 'my_'
 *     $file->declarations[0]->form?->value // => 'code'
 */
final class Define implements Declaration
{
    /**
     * @param string $variable The variable name
     * @param string|null $value The keyword, the decoded string, or the code without its braces; null when omitted
     * @param DefineForm|null $form Which form the value was written in, null when omitted
     * @param Location $location Where the directive is written
     */
    public function __construct(
        public readonly string $variable,
        public readonly ?string $value,
        public readonly ?DefineForm $form,
        public readonly Location $location,
    ) {
    }

    /**
     * Answers where the declaration begins.
     *
     * @return Location Line and column of the percent sign
     */
    public function location(): Location
    {
        return $this->location;
    }
}
