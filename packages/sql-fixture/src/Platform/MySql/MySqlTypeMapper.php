<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql;

use Faker\Generator;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\TypeMapper\TypeMapperInterface;

final class MySqlTypeMapper implements TypeMapperInterface
{
    public function generate(Generator $faker, ColumnDefinition $column): mixed
    {
        if ($column->autoIncrement || $column->generated) {
            return null;
        }

        $value = $this->generateValue($faker, $column);

        if ($column->nullable && $value !== null) {
            $shouldUseDefault = $faker->boolean(10);
            if ($shouldUseDefault) {
                return $column->default;
            }
        }

        return $value;
    }

    private function generateValue(Generator $faker, ColumnDefinition $column): mixed
    {
        $type = strtoupper($column->type);

        return match ($type) {
            'TINYINT' => $this->generateTinyInt($faker, $column),
            'SMALLINT' => $this->generateSmallInt($faker, $column),
            'MEDIUMINT' => $this->generateMediumInt($faker, $column),
            'INT', 'INTEGER' => $this->generateInt($faker, $column),
            'BIGINT' => $this->generateBigInt($faker, $column),
            'FLOAT' => $faker->randomFloat(2, -1000.0, 1000.0),
            'DOUBLE', 'REAL' => $faker->randomFloat(4, -1000000.0, 1000000.0),
            'DECIMAL', 'NUMERIC', 'DEC', 'FIXED' => $this->generateDecimal($faker, $column),
            'BIT' => $this->generateBit($faker, $column),

            'CHAR' => $this->generateChar($faker, $column),
            'VARCHAR' => $this->generateVarchar($faker, $column),
            'TINYTEXT' => substr($faker->text(255), 0, 255),
            'TEXT' => $faker->paragraphs(2, true),
            'MEDIUMTEXT' => $faker->paragraphs(3, true),
            'LONGTEXT' => $faker->paragraphs(5, true),

            'BINARY' => $this->generateBinary($faker, $column),
            'VARBINARY' => $this->generateVarbinary($faker, $column),
            'TINYBLOB' => random_bytes(max(1, $faker->numberBetween(1, 255))),
            'BLOB' => random_bytes(max(1, $faker->numberBetween(1, 1000))),
            'MEDIUMBLOB' => random_bytes(max(1, $faker->numberBetween(1, 1000))),
            'LONGBLOB' => random_bytes(max(1, $faker->numberBetween(1, 1000))),

            'ENUM' => $this->generateEnum($faker, $column),
            'SET' => $this->generateSet($faker, $column),

            'DATE' => $faker->date('Y-m-d'),
            'TIME' => $faker->time('H:i:s'),
            'DATETIME' => $faker->dateTime()->format('Y-m-d H:i:s'),
            'TIMESTAMP' => $faker->dateTimeBetween('1970-01-01', '2038-01-19')->format('Y-m-d H:i:s'),
            'YEAR' => $faker->numberBetween(1901, 2155),

            'JSON' => json_encode([
                'key' => $faker->text(20),
                'value' => $faker->numberBetween(1, 100),
            ]),

            'POINT' => $this->generatePoint($faker),
            'LINESTRING' => $this->generateLineString($faker),
            'POLYGON' => $this->generatePolygon($faker),
            'MULTIPOINT' => $this->generateMultiPoint($faker),
            'MULTILINESTRING' => $this->generateMultiLineString($faker),
            'MULTIPOLYGON' => $this->generateMultiPolygon($faker),
            'GEOMETRY' => $this->generatePoint($faker),
            'GEOMETRYCOLLECTION' => $this->generateGeometryCollection($faker),

            'BOOL', 'BOOLEAN' => $faker->boolean(),

            default => $faker->text(50),
        };
    }

    private function generateTinyInt(Generator $faker, ColumnDefinition $column): int|bool
    {
        if ($column->length === 1) {
            return $faker->boolean();
        }

        if ($column->unsigned) {
            return $faker->numberBetween(0, 255);
        }
        return $faker->numberBetween(-128, 127);
    }

    private function generateSmallInt(Generator $faker, ColumnDefinition $column): int
    {
        if ($column->unsigned) {
            return $faker->numberBetween(0, 65535);
        }
        return $faker->numberBetween(-32768, 32767);
    }

    private function generateMediumInt(Generator $faker, ColumnDefinition $column): int
    {
        if ($column->unsigned) {
            return $faker->numberBetween(0, 16777215);
        }
        return $faker->numberBetween(-8388608, 8388607);
    }

    private function generateInt(Generator $faker, ColumnDefinition $column): int
    {
        if ($column->unsigned) {
            return $faker->numberBetween(0, 4294967295);
        }
        return $faker->numberBetween(-2147483648, 2147483647);
    }

    private function generateBigInt(Generator $faker, ColumnDefinition $column): int
    {
        if ($column->unsigned) {
            return $faker->numberBetween(0, PHP_INT_MAX);
        }
        return $faker->numberBetween(PHP_INT_MIN, PHP_INT_MAX);
    }

    private function generateDecimal(Generator $faker, ColumnDefinition $column): float
    {
        $precision = $column->precision ?? 10;
        $scale = $column->scale ?? 0;
        $integerDigits = $precision - $scale;

        $max = (float) pow(10, $integerDigits) - 1;
        $min = $column->unsigned ? 0.0 : -$max;

        return $faker->randomFloat($scale, $min, $max);
    }

    private function generateBit(Generator $faker, ColumnDefinition $column): int
    {
        $length = $column->length ?? 1;
        $max = (int) pow(2, $length) - 1;
        return $faker->numberBetween(0, $max);
    }

    private function generateChar(Generator $faker, ColumnDefinition $column): string
    {
        $length = $column->length ?? 1;
        $pattern = str_repeat('?', $length);
        $result = $faker->lexify($pattern);
        return substr($result, 0, $length);
    }

    private function generateVarchar(Generator $faker, ColumnDefinition $column): string
    {
        $maxLength = $column->length ?? 255;
        $text = $faker->text(min($maxLength, 200));
        return substr($text, 0, $maxLength);
    }

    private function generateBinary(Generator $faker, ColumnDefinition $column): string
    {
        unset($faker);
        $length = max(1, $column->length ?? 1);
        return random_bytes($length);
    }

    private function generateVarbinary(Generator $faker, ColumnDefinition $column): string
    {
        $maxLength = max(1, $column->length ?? 255);
        $length = max(1, $faker->numberBetween(1, $maxLength));
        return random_bytes($length);
    }

    private function generateEnum(Generator $faker, ColumnDefinition $column): ?string
    {
        $values = $column->enumValues ?? [];
        if ($values === []) {
            return null;
        }
        /** @var string $element */
        $element = $faker->randomElement($values);
        return $element;
    }

    private function generateSet(Generator $faker, ColumnDefinition $column): ?string
    {
        $values = $column->enumValues ?? [];
        if ($values === []) {
            return null;
        }
        $count = $faker->numberBetween(1, count($values));
        $selected = $faker->randomElements($values, $count);
        return implode(',', $selected);
    }

    private function generatePoint(Generator $faker): string
    {
        $lon = $faker->longitude();
        $lat = $faker->latitude();
        return sprintf('POINT(%f %f)', $lon, $lat);
    }

    private function generateLineString(Generator $faker): string
    {
        $pointCount = $faker->numberBetween(2, 4);
        $points = [];
        for ($i = 0; $i < $pointCount; $i++) {
            $points[] = sprintf('%f %f', $faker->longitude(), $faker->latitude());
        }
        return 'LINESTRING(' . implode(',', $points) . ')';
    }

    private function generatePolygon(Generator $faker): string
    {
        $baseLon = $faker->longitude(-170, 170);
        $baseLat = $faker->latitude(-80, 80);
        $offset = $faker->randomFloat(2, 0.1, 1.0);

        $points = [
            sprintf('%f %f', $baseLon, $baseLat),
            sprintf('%f %f', $baseLon + $offset, $baseLat),
            sprintf('%f %f', $baseLon + $offset, $baseLat + $offset),
            sprintf('%f %f', $baseLon, $baseLat + $offset),
            sprintf('%f %f', $baseLon, $baseLat),
        ];

        return 'POLYGON((' . implode(',', $points) . '))';
    }

    private function generateMultiPoint(Generator $faker): string
    {
        $pointCount = $faker->numberBetween(2, 4);
        $points = [];
        for ($i = 0; $i < $pointCount; $i++) {
            $points[] = sprintf('(%f %f)', $faker->longitude(), $faker->latitude());
        }
        return 'MULTIPOINT(' . implode(',', $points) . ')';
    }

    private function generateMultiLineString(Generator $faker): string
    {
        $lineCount = $faker->numberBetween(2, 3);
        $lines = [];
        for ($i = 0; $i < $lineCount; $i++) {
            $pointCount = $faker->numberBetween(2, 3);
            $points = [];
            for ($j = 0; $j < $pointCount; $j++) {
                $points[] = sprintf('%f %f', $faker->longitude(), $faker->latitude());
            }
            $lines[] = '(' . implode(',', $points) . ')';
        }
        return 'MULTILINESTRING(' . implode(',', $lines) . ')';
    }

    private function generateMultiPolygon(Generator $faker): string
    {
        $polygons = [];
        for ($i = 0; $i < 2; $i++) {
            $baseLon = $faker->longitude(-170, 170);
            $baseLat = $faker->latitude(-80, 80);
            $offset = $faker->randomFloat(2, 0.1, 0.5);

            $points = [
                sprintf('%f %f', $baseLon, $baseLat),
                sprintf('%f %f', $baseLon + $offset, $baseLat),
                sprintf('%f %f', $baseLon + $offset, $baseLat + $offset),
                sprintf('%f %f', $baseLon, $baseLat),
            ];
            $polygons[] = '((' . implode(',', $points) . '))';
        }
        return 'MULTIPOLYGON(' . implode(',', $polygons) . ')';
    }

    private function generateGeometryCollection(Generator $faker): string
    {
        $point = $this->generatePoint($faker);
        $lineString = $this->generateLineString($faker);
        return "GEOMETRYCOLLECTION({$point},{$lineString})";
    }
}
