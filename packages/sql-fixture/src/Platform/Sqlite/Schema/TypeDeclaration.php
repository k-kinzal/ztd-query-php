<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite\Schema;

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
            return 'BLOB';
        }

        if (preg_match('/^(\w+)/i', $rest, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        return 'BLOB';
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

        if (preg_match('/^(\w+)\s*\(\s*(\d+)\s*(?:,\s*(\d+)\s*)?\)/i', $rest, $typeMatches) === 1) {
            $type = strtoupper($typeMatches[1]);
            if (isset($typeMatches[3])) {
                $precision = (int) $typeMatches[2];
                $scale = (int) $typeMatches[3];
            } else {
                $length = (int) $typeMatches[2];
            }
        }
        return new \SqlFixture\Schema\TypeShape($type, $length, $precision, $scale);
    }
}
