<?php

declare(strict_types=1);

namespace BisonParser;

use BisonParser\Ast\Location;
use RuntimeException;

/**
 * The grammar file is not written the way Bison would accept it.
 *
 * @visibility public
 *
 * @example Reporting where a file is wrong
 *     $exception = \BisonParser\SyntaxException::unexpected("a rule or ';'", "'|'", new \BisonParser\Ast\Location(3, 1));
 *     $exception->getMessage() // => "Expected a rule or ';' but found '|' at 3:1"
 *     (string) $exception->location // => "3:1"
 */
final class SyntaxException extends RuntimeException
{
    /**
     * @param string $message What is wrong
     * @param Location $location Where it is wrong
     */
    public function __construct(string $message, public readonly Location $location)
    {
        parent::__construct("{$message} at {$location}");
    }

    /**
     * Describes something other than what the grammar language allows at a position.
     *
     * @param string $expected What was expected
     * @param string $found What was met instead
     * @param Location $location Where it was met
     *
     * @return self The exception
     */
    public static function unexpected(string $expected, string $found, Location $location): self
    {
        return new self("Expected {$expected} but found {$found}", $location);
    }

    /**
     * Describes a construct that never closes.
     *
     * @param string $construct What was opened
     * @param string $closer What should have closed it
     * @param Location $location Where it was opened
     *
     * @return self The exception
     */
    public static function unterminated(string $construct, string $closer, Location $location): self
    {
        return new self("Missing '{$closer}' closing the {$construct} opened", $location);
    }

    /**
     * Describes text that is not part of the grammar language.
     *
     * @param string $what The problem, as Bison would word it
     * @param Location $location Where it is
     *
     * @return self The exception
     */
    public static function invalid(string $what, Location $location): self
    {
        return new self(ucfirst($what), $location);
    }
}
