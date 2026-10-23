<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary\Program;

use MySqlMemory\Dictionary\Routine;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Public signatures and characteristics of installed routines, independent of executable declarations.
 *
 * The catalog contains no SQL implementation bodies. Its attributes come from the documented
 * INFORMATION_SCHEMA tables; a routine's installation dates are separate instance state.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-routines-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/information-schema-parameters-table.html.
 *
 * @visibility MySqlMemory
 */
final class Installed
{
    /**
     * @param array<string, int|string|null> $metadata The static ROUTINES columns, excluding identity and installation dates
     * @param list<array<string, int|string|null>> $parameters The PARAMETERS rows, including a function's return value
     */
    public function __construct(public readonly array $metadata, public readonly array $parameters)
    {
    }

    /**
     * Creates the dictionary entry, retaining the metadata without inventing an executable body.
     *
     * @param array{int, int} $timestamps The creation and modification epochs of the installation
     */
    public function routine(array $timestamps): Routine
    {
        $row = $this->metadata;
        [$user, $host] = explode('@', (string) $row['DEFINER'], 2) + [1 => ''];

        return new Routine('sys', (string) $row['ROUTINE_NAME'], [$user, $host], '', (string) $row['DTD_IDENTIFIER'], '', (string) $row['SQL_DATA_ACCESS'], $row['IS_DETERMINISTIC'] === 'YES', (string) $row['SECURITY_TYPE'], (string) $row['ROUTINE_COMMENT'], (string) $row['SQL_MODE'], gmdate('Y-m-d H:i:s', $timestamps[0]), gmdate('Y-m-d H:i:s', $timestamps[1]), [(string) $row['CHARACTER_SET_CLIENT'], (string) $row['COLLATION_CONNECTION'], (string) $row['DATABASE_COLLATION']], null, $this);
    }

    /**
     * Answers the domain of the installed signature, without needing an implementation body.
     */
    public function returned(): Domain
    {
        $row = $this->metadata;
        if ($row['ROUTINE_TYPE'] !== 'FUNCTION') {
            return Domain::null();
        }
        if ($row['DATA_TYPE'] === 'tinyint' || $row['DATA_TYPE'] === 'bigint') {
            return Domain::integer($row['DATA_TYPE'] === 'tinyint' ? Field::Tiny : Field::LongLong, $row['DATA_TYPE'] === 'tinyint' ? 3 : 20, true)->withNullable(true);
        }
        $field = match ($row['DATA_TYPE']) {
            'enum' => Field::Enum,
            'text' => Field::Blob,
            'longtext' => Field::LongBlob,
            default => Field::VarString,
        };
        $members = $row['DATA_TYPE'] === 'enum' ? explode("','", substr((string) $row['DTD_IDENTIFIER'], 6, -2)) : [];

        return new Domain(Kind::String, $field, (int) $row['CHARACTER_MAXIMUM_LENGTH'], 0, false, Collation::named((string) $row['COLLATION_NAME']), true, $members);
    }
}
