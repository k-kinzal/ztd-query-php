<?php

declare(strict_types=1);

namespace SqlFixture\Fixture;

use Faker\Generator;
use SqlFixture\Hydrator\HydratorInterface;
use SqlFixture\Schema\TableSchema;
use SqlFixture\TypeMapper\TypeMapperInterface;

/**
 * Generates schema-driven rows using injected value and hydration contracts.
 */
final class RowGenerator implements RowGeneration
{
    /**
     * Supplies the behavior needed to realize a row without selecting a platform.
     */
    public function __construct(
        private readonly Generator $faker,
        private readonly TypeMapperInterface $typeMapper,
        private readonly HydratorInterface $hydrator,
    ) {
    }

    /**
     * @template T of object
     * @param array<mixed> $overrides
     * @param class-string<T>|null $className
     * @return ($className is null ? array<string, mixed> : T)
     */
    public function generate(
        TableSchema $schema,
        array $overrides = [],
        ?string $className = null,
    ): array|object {
        (new Validation\OverrideValidator())->assertOverridesFitSchema($schema, $overrides);

        $data = [];

        foreach ($schema->columns as $column) {
            $columnName = $column->name;

            if (array_key_exists($columnName, $overrides)) {
                $data[$columnName] = $overrides[$columnName];
                continue;
            }

            if ($column->autoIncrement || $column->generated) {
                continue;
            }

            $data[$columnName] = $this->typeMapper->generate($this->faker, $column);
        }

        if ($className === null) {
            return $data;
        }

        return $this->hydrator->hydrate($data, $className);
    }
}
