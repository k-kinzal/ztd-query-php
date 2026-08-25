<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql;

use Faker\Generator;
use LogicException;
use SqlFixture\Schema\ColumnDefinition;

/**
 * Picks from the members a MySQL ENUM or SET column declares.
 *
 * Both types accept nothing but what they list. A column whose members could
 * not be read from the schema therefore has no value that would be accepted,
 * and answering null says so rather than inventing one the server will reject.
 */
final class MySqlEnumerationSample
{
    /**
     * Picks one of the members an ENUM column declares.
     *
     * @param Generator $faker Source of the choice
     * @param ColumnDefinition $column Column the value is for
     *
     * @return string|null One member, or null when the column declares none
     */
    public function one(Generator $faker, ColumnDefinition $column): ?string
    {
        $members = $column->enumValues ?? [];
        if ($members === []) {
            return null;
        }

        /** @var string $member */
        $member = $faker->randomElement($members);

        return $member;
    }

    /**
     * Picks some of the members a SET column declares, written as SET stores them.
     *
     * @param Generator $faker Source of the choice
     * @param ColumnDefinition $column Column the value is for
     *
     * @return string|null The chosen members separated by commas, or null when the column declares none
     *
     * @throws LogicException When a chosen member is not a string
     */
    public function some(Generator $faker, ColumnDefinition $column): ?string
    {
        $members = $column->enumValues ?? [];
        if ($members === []) {
            return null;
        }

        $chosen = [];
        foreach ($faker->randomElements($members, $faker->numberBetween(1, count($members))) as $member) {
            if (!is_string($member)) {
                throw new LogicException('Faker returned a non-string SET value.');
            }
            $chosen[] = $member;
        }

        return implode(',', $chosen);
    }
}
