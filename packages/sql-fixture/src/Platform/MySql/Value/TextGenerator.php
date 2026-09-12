<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Value;

use Faker\Generator;
use LogicException;
use SqlFixture\Schema\ColumnDefinition;

/**
 * Generates textual values for the dialect's declared column type.
 *
 * @visibility root
 */
final class TextGenerator
{
    /**
     * Generates a value for a supported member of this type family.
     * @throws LogicException
     */
    public function generate(Generator $faker, ColumnDefinition $column): ?string
    {
        return match (strtoupper($column->type)) {
            'CHAR' => (new StringGenerator())->generateChar($faker, $column),
            'VARCHAR' => (new StringGenerator())->generateVarchar($faker, $column),
            'TINYTEXT' => substr($faker->text(255), 0, 255),
            'TEXT' => (new \SqlFixture\TypeMapper\ParagraphGenerator())->generate($faker, 2),
            'MEDIUMTEXT' => (new \SqlFixture\TypeMapper\ParagraphGenerator())->generate($faker, 3),
            'LONGTEXT' => (new \SqlFixture\TypeMapper\ParagraphGenerator())->generate($faker, 5),
            'BINARY' => (new StringGenerator())->generateBinary($faker, $column),
            'VARBINARY' => (new StringGenerator())->generateVarbinary($faker, $column),
            'TINYBLOB' => random_bytes(max(1, $faker->numberBetween(1, 255))),
            'BLOB' => random_bytes(max(1, $faker->numberBetween(1, 1000))),
            'MEDIUMBLOB' => random_bytes(max(1, $faker->numberBetween(1, 1000))),
            'LONGBLOB' => random_bytes(max(1, $faker->numberBetween(1, 1000))),
            'ENUM' => (new StringGenerator())->generateEnum($faker, $column),
            'SET' => (new StringGenerator())->generateSet($faker, $column),
            default => throw new LogicException('Unsupported textual type: ' . $column->type),
        };
    }
}
