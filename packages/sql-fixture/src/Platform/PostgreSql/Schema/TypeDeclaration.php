<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

/**
 * TypeDeclaration.
 *
 * @visibility root
 */
final class TypeDeclaration
{
    /**
     * Reads the type name without consuming column constraints.
     */
    public function extractType(string $rest): string
    {
        if ($rest === '') {
            return 'TEXT';
        }

        $multiWordTypes = [
            'DOUBLE PRECISION',
            'TIMESTAMP WITH TIME ZONE',
            'TIMESTAMP WITHOUT TIME ZONE',
            'TIME WITH TIME ZONE',
            'TIME WITHOUT TIME ZONE',
            'CHARACTER VARYING',
        ];

        $upperRest = strtoupper($rest);
        foreach ($multiWordTypes as $multiWord) {
            if (str_starts_with($upperRest, $multiWord)) {
                return $multiWord;
            }
        }

        if (preg_match('/^(\w+(?:\[\])?)/i', $rest, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        return 'TEXT';
    }

    /**
     * Recognizes the dialect numeric types that accept precision and scale.
     */
    public function isDecimalType(string $type): bool
    {
        return in_array(strtoupper($type), ['DECIMAL', 'NUMERIC', 'DEC'], true);
    }

    /**
     * Interprets the declared type parameters before column constraints are applied.
     */
    public function parse(string $rest): \SqlFixture\Schema\TypeShape
    {
        $type = (new TypeDeclaration())->extractType($rest);
        $length = null;
        $precision = null;
        $scale = null;
        $autoIncrement = false;

        $upperType = strtoupper($type);
        if (in_array($upperType, ['SERIAL', 'BIGSERIAL', 'SMALLSERIAL'], true)) {
            $autoIncrement = true;
            $type = match ($upperType) {
                'SERIAL' => 'INTEGER',
                'BIGSERIAL' => 'BIGINT',
                'SMALLSERIAL' => 'SMALLINT',
            };
        }

        if (preg_match('/^(\w+(?:\s+\w+)?)\s*\(\s*(\d+)\s*(?:,\s*(\d+)\s*)?\)/i', $rest, $typeMatches) === 1) {
            $parsedType = strtoupper($typeMatches[1]);
            if (!$autoIncrement) {
                $type = $parsedType;
            }
            if (isset($typeMatches[3])) {
                $precision = (int) $typeMatches[2];
                $scale = (int) $typeMatches[3];
            } else {
                if ((new TypeDeclaration())->isDecimalType($parsedType)) {
                    $precision = (int) $typeMatches[2];
                    $scale = 0;
                } else {
                    $length = (int) $typeMatches[2];
                }
            }
        }

        if (str_ends_with($type, '[]')) {
            $type = substr($type, 0, -2) . '_ARRAY';
        }
        return new \SqlFixture\Schema\TypeShape($type, $length, $precision, $scale, $autoIncrement);
    }
}
