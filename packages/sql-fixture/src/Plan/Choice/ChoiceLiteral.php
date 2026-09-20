<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Choice;

use SqlFixture\Plan\Exception\UnexpectedPlanTokenException;
use SqlFixture\Plan\Parsing\PlanStatements;
use SqlFixture\Plan\Parsing\RelationCursor;

/**
 * Reads and prints typed discriminator literals without PHP array-key coercion.
 * @visibility root
 */
final class ChoiceLiteral
{
    /**
     * Consumes a string, integer, boolean or NULL literal.
     * @throws UnexpectedPlanTokenException
     */
    public function read(RelationCursor $cursor): string|int|bool|null
    {
        if ($cursor->peek() === "'") {
            $end = (new PlanStatements())->quotedEnd($cursor->source, $cursor->offset);
            $value = substr($cursor->source, $cursor->offset + 1, $end - $cursor->offset - 1);
            $cursor->offset = $end + 1;
            return str_replace("''", "'", $value);
        }
        if (preg_match('/\G(?:null|true|false|-?(?:0|[1-9][0-9]*))(?=\s|\{)/', $cursor->source, $match, 0, $cursor->offset) !== 1) {
            throw new UnexpectedPlanTokenException($cursor->source, $cursor->offset, 'a quoted string, integer, boolean or null');
        }
        $cursor->offset += strlen($match[0]);
        return match ($match[0]) {
            'null' => null,
            'true' => true,
            'false' => false,
            default => $this->integer($match[0], $cursor),
        };
    }

    /**
     * Rejects values outside the current PHP integer range.
     * @throws UnexpectedPlanTokenException
     */
    public function integer(string $literal, RelationCursor $cursor): int
    {
        $value = filter_var($literal, FILTER_VALIDATE_INT);
        if (!is_int($value)) {
            throw new UnexpectedPlanTokenException($cursor->source, $cursor->offset, 'an integer within the PHP integer range');
        }
        return $value;
    }

    /**
     * Preserves both the type and the contents of a case value.
     */
    public function print(string|int|bool|null $value): string
    {
        if (is_string($value)) {
            return "'" . str_replace("'", "''", $value) . "'";
        }
        return match ($value) {
            null => 'null',
            true => 'true',
            false => 'false',
            default => (string) $value,
        };
    }
}
