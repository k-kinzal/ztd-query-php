<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Access\Load;

/**
 * Splits fixed-width runtime records by the destination field widths.
 * Absent trailing fields remain absent so the writer can preserve LOAD's
 * short-record warning and empty-field defaults separately.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/load-data.html.
 *
 * @visibility MySqlMemory
 */
final class FixedRecords
{
    /**
     * @param array<string, string> $separators Effective line options
     * @param list<int> $widths File widths in destination order
     * @return list<list<string|null>> The available fixed fields of each record
     */
    public function read(string $input, array $separators, array $widths, int $ignored = 0): array
    {
        $lines = (new SeparatedRecords())->read($input, [...$separators, 'field:TERMINATED' => '', 'field:ENCLOSED' => '', 'field:ESCAPED' => ''], $ignored);
        $records = [];
        foreach ($lines as $line) {
            $bytes = $line[0] ?? '';
            $fields = [];
            $offset = 0;
            foreach ($widths as $width) {
                if ($offset >= strlen($bytes)) {
                    break;
                }
                $fields[] = rtrim(substr($bytes, $offset, $width), ' ');
                $offset += $width;
            }
            if ($offset < strlen($bytes)) {
                $fields[] = substr($bytes, $offset);
            }
            $records[] = $fields;
        }
        return $records;
    }
}
