<?php

declare(strict_types=1);

namespace Deriver\Exception;

use RuntimeException;
use Throwable;

/**
 * A source could not be parsed using the requested PHP grammar.
 * @visibility public
 * @example Inspecting the failing source
 *     (new \Deriver\Exception\ParserException('Unexpected token', 'app.php', 3))->path // => 'app.php'
 */
final class ParserException extends RuntimeException
{
    /**
     * @param string $message Parser diagnostic
     * @param string $path Path of the source being parsed
     * @param int $sourceLine One-based line in that source
     * @param Throwable|null $previous Original parser error
     */
    public function __construct(string $message, public readonly string $path, public readonly int $sourceLine = 1, ?Throwable $previous = null)
    {
        parent::__construct($path . ':' . $sourceLine . ': ' . $message, 0, $previous);
    }
}
