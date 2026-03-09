<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

use InvalidArgumentException;

final readonly class ProductionRule
{
    public string $name;

    /**
     * @var list<Production>
     */
    public array $alternatives;

    /**
     * @param array<array-key, mixed> $alternatives
     */
    public function __construct(string $name, array $alternatives)
    {
        if ($name === '') {
            throw new InvalidArgumentException('Production rule name must be non-empty.');
        }

        if (!array_is_list($alternatives)) {
            throw new InvalidArgumentException('Production rule alternatives must be a list.');
        }

        foreach ($alternatives as $alternative) {
            if (!$alternative instanceof Production) {
                throw new InvalidArgumentException('Production rule alternatives must contain only Production values.');
            }
        }

        /** @var list<Production> $alternatives */
        $this->name = $name;
        $this->alternatives = $alternatives;
    }
}
