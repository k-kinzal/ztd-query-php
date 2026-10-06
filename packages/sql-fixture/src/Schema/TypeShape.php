<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

/**
 * The size and numeric precision carried by a parsed SQL type declaration.
 *
 * @visibility root
 */
final class TypeShape
{
    /**
     * Records the normalized type while preserving an absent length or scale.
     */
    public function __construct(
        public readonly string $type,
        public readonly ?int $length = null,
        public readonly ?int $precision = null,
        public readonly ?int $scale = null,
        public readonly bool $autoIncrement = false,
    ) {
    }

    /**
     * Reads the numbers a type declaration carries into the shape they describe.
     *
     * Two numbers are a precision and a scale whatever the type is spelled,
     * one number is a precision for the types that take one and a length for
     * every other type.
     *
     * @param list<int> $numbers The numbers the declaration carries, in order
     */
    public static function fromNumbers(string $type, array $numbers, bool $decimal = false, bool $autoIncrement = false): self
    {
        if (count($numbers) >= 2) {
            return new self($type, null, $numbers[0], $numbers[1], $autoIncrement);
        }
        if ($numbers === []) {
            return new self($type, autoIncrement: $autoIncrement);
        }

        return $decimal
            ? new self($type, null, $numbers[0], 0, $autoIncrement)
            : new self($type, $numbers[0], autoIncrement: $autoIncrement);
    }
}
