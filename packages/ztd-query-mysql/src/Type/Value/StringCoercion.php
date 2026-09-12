<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Type\Value;

use RuntimeException;
use Stringable;

/**
 * String Value.
 *
 */
final class StringCoercion
{
    /**
     * String Value for the supplied MySQL input.
     * @throws RuntimeException
     */
    public function stringValue(mixed $value): string
    {
        if ($value instanceof Stringable) {
            return (string) $value;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_resource($value) && get_resource_type($value) === 'stream') {
            return $this->readStream($value);
        }

        throw new RuntimeException('Unsupported value type for CTE shadowing.');
    }

    /**
     * @param resource $stream
     */
    public function readStream($stream): string
    {
        $position = ftell($stream);
        rewind($stream);
        $contents = stream_get_contents($stream);
        if ($position !== false) {
            fseek($stream, $position);
        }

        return $contents === false ? '' : $contents;
    }
}
