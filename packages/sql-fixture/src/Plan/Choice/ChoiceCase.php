<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Choice;

use SqlFixture\Plan\Relation;

/**
 * The relations activated by one discriminator value.
 * @visibility root
 */
final class ChoiceCase
{
    /**
     * @param list<Relation> $relations
     */
    public function __construct(
        public readonly string|int|bool|null $value,
        public readonly array $relations,
    ) {
    }
}
