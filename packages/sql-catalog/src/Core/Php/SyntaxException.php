<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Php;

use RuntimeException;
use Throwable;

/**
 * A source file the PHP parser could not read.
 *
 * @visibility root
 */
final class SyntaxException extends RuntimeException
{
    /**
     * @param string $path The file that failed to parse
     * @param string $reason The parser's explanation
     * @param Throwable|null $previous The parser error being wrapped
     */
    public function __construct(
        public readonly string $path,
        string $reason,
        ?Throwable $previous = null,
    ) {
        parent::__construct(sprintf('Cannot parse "%s": %s.', $path, $reason), 0, $previous);
    }
}
