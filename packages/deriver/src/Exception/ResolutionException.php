<?php

declare(strict_types=1);

namespace Deriver\Exception;

use RuntimeException;
use Throwable;

/**
 * Parsed source contains invalid names, lexical scope, or expression constraints.
 * @visibility public
 * @example Inspecting a failed name resolution
 *     (new \Deriver\Exception\ResolutionException('Duplicate alias', 'app.php', 3))->path // => 'app.php'
 */
final class ResolutionException extends RuntimeException
{
    /**
     * @param string $message Resolution diagnostic
     * @param string $path Source containing the invalid construct
     * @param int $sourceLine One-based source line
     * @param Throwable|null $previous Original diagnostic
     */
    public function __construct(string $message, public readonly string $path, public readonly int $sourceLine = 1, ?Throwable $previous = null)
    {
        parent::__construct($path . ':' . $sourceLine . ': ' . $message, 0, $previous);
    }
}
