<?php

declare(strict_types=1);

namespace LemonParser;

use LemonParser\Ast\Location;
use RuntimeException;

/**
 * Raised where Lemon would report an error in the grammar file.
 *
 * The message is worded as Lemon words it and says where the problem is.
 *
 * @visibility public
 *
 * @example Reporting where a file is wrong
 *     $exception = new \LemonParser\SyntaxException('Illegal character on RHS of rule: "?".', new \LemonParser\Ast\Location(3, 12));
 *     $exception->getMessage() // => 'Illegal character on RHS of rule: "?". at 3:12'
 *     (string) $exception->location // => "3:12"
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
}
