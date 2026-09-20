<?php

declare(strict_types=1);

namespace BisonParser\Ast;

/**
 * The host code after the second `%%`, kept exactly as written.
 *
 * @visibility public
 *
 * @example Reading the epilogue
 *     $file = (new \BisonParser\Parser())->parse("%%\ns: 'a';\n%%\nint main() {}\n");
 *     $file->epilogue?->code // => "\nint main() {}\n"
 */
final class Epilogue
{
    /**
     * @param string $code Everything after the second `%%`
     * @param Location $location Where the code begins
     */
    public function __construct(
        public readonly string $code,
        public readonly Location $location,
    ) {
    }
}
