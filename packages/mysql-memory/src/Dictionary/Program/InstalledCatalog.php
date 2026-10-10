<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary\Program;

use MySqlMemory\Dictionary\Schema;
use SqlSemantics\Contract\GrammarRelease;

/**
 * The sys routine metadata installed with MySQL 5.7 and later distributions.
 *
 * The static attributes were observed independently on 8.0.44, 8.4.7 and 9.1.0 and agree.
 * Installation dates are supplied as initial state, defaulting to the instance's creation time.
 * MySQL 5.7 has its own observed catalog; MySQL 5.6 installs no sys schema.
 *
 * @visibility MySqlMemory
 */
final class InstalledCatalog
{
    /**
     * @var array<string, list<Installed>> The immutable public signatures shared by instances, by release
     */
    private static array $entries = [];

    /**
     * Installs the catalog in a schema, with an independent timestamp for each routine when given.
     *
     * @param array<string, array{int, int}> $timestamps Creation and modification epochs, keyed by FUNCTION:name or PROCEDURE:name
     */
    public static function install(Schema $schema, GrammarRelease $release, array $timestamps = []): void
    {
        if ($release === GrammarRelease::MySql5651) {
            return;
        }
        $now = time();
        foreach (self::entries($release) as $entry) {
            $name = (string) $entry->metadata['ROUTINE_NAME'];
            $kind = (string) $entry->metadata['ROUTINE_TYPE'];
            $routine = $entry->routine($timestamps[$kind . ':' . $name] ?? [$now, $now]);
            if ($kind === 'FUNCTION') {
                $schema->functions[strtolower($name)] = $routine;
            } else {
                $schema->procedures[strtolower($name)] = $routine;
            }
        }
    }

    /**
     * Answers the immutable public signatures for catalog inspection; each instance owns mutable dictionary entries.
     *
     * @return list<Installed>
     */
    public static function entries(GrammarRelease $release = GrammarRelease::MySql847): array
    {
        if (isset(self::$entries[$release->value])) {
            return self::$entries[$release->value];
        }
        $data = self::data($release);
        $entries = [];
        foreach ($data['routines'] as $row) {
            $metadata = array_combine(explode(',', 'ROUTINE_NAME,ROUTINE_TYPE,DATA_TYPE,CHARACTER_MAXIMUM_LENGTH,CHARACTER_OCTET_LENGTH,NUMERIC_PRECISION,NUMERIC_SCALE,DATETIME_PRECISION,CHARACTER_SET_NAME,COLLATION_NAME,DTD_IDENTIFIER,ROUTINE_BODY,EXTERNAL_NAME,EXTERNAL_LANGUAGE,PARAMETER_STYLE,IS_DETERMINISTIC,SQL_DATA_ACCESS,SQL_PATH,SECURITY_TYPE,SQL_MODE,ROUTINE_COMMENT,DEFINER,CHARACTER_SET_CLIENT,COLLATION_CONNECTION,DATABASE_COLLATION'), $row);
            $entries[] = new Installed($metadata, self::parameters($data['parameters'], (string) $metadata['ROUTINE_TYPE'], (string) $metadata['ROUTINE_NAME']));
        }

        return self::$entries[$release->value] = $entries;
    }

    /**
     * Answers the metadata captured without reading any routine implementation.
     *
     * @return array{routines: list<list<int|string|null>>, parameters: list<list<int|string|null>>}
     */
    public static function data(GrammarRelease $release = GrammarRelease::MySql847): array
    {
        if ($release === GrammarRelease::MySql5651) {
            return ['routines' => [], 'parameters' => []];
        }
        $version = $release === GrammarRelease::MySql5744 ? '5.7.44' : '8.4.7';
        /** @var array{routines: list<list<int|string|null>>, parameters: list<list<int|string|null>>} $data */
        $data = require dirname(__DIR__, 3) . '/resources/system-routines/mysql-' . $version . '.php';

        return $data;
    }

    /**
     * Answers the declared parameters and optional return value of one installed routine.
     *
     * @param list<list<int|string|null>> $rows The captured parameter rows
     * @return list<array<string, int|string|null>>
     */
    public static function parameters(array $rows, string $kind, string $name): array
    {
        $parameters = [];
        foreach ($rows as $row) {
            if ($row[0] === $name && $row[13] === $kind) {
                $parameters[] = ['SPECIFIC_CATALOG' => 'def', 'SPECIFIC_SCHEMA' => 'sys'] + array_combine(explode(',', 'SPECIFIC_NAME,ORDINAL_POSITION,PARAMETER_MODE,PARAMETER_NAME,DATA_TYPE,CHARACTER_MAXIMUM_LENGTH,CHARACTER_OCTET_LENGTH,NUMERIC_PRECISION,NUMERIC_SCALE,DATETIME_PRECISION,CHARACTER_SET_NAME,COLLATION_NAME,DTD_IDENTIFIER,ROUTINE_TYPE'), $row);
            }
        }

        return $parameters;
    }
}
