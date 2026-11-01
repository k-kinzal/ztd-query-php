<?php

declare(strict_types=1);

namespace MySqlMemory\Server;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Transfers initial table fixtures through the child process pipe, without command-line size limits.
 *
 * @visibility MySqlMemory
 */
final class InitialTables
{
    /**
     * Writes all fixture bytes; partial pipe writes are continued before closing the input.
     *
     * @param resource $stream The child process input
     * @param array<string, list<list<string>>> $tables The initial table rows
     * @throws InvalidArgumentException When a value cannot be encoded
     * @throws RuntimeException When the child process stops reading
     */
    public function write($stream, array $tables): void
    {
        try {
            $json = json_encode($tables, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('The initial table fixture must contain UTF-8 text.', previous: $error);
        }
        $offset = 0;
        while ($offset < strlen($json)) {
            $count = fwrite($stream, substr($json, $offset));
            if ($count === false || $count === 0) {
                throw new RuntimeException('The server process did not read its initial table fixture.');
            }
            $offset += $count;
        }
    }

    /**
     * Reads a fixture, validating its table names, row widths and string cells.
     *
     * @param resource $stream The process input
     * @return array<string, list<list<string>>>
     * @throws InvalidArgumentException When input is not an initial table fixture
     * @throws RuntimeException When input cannot be read
     */
    public function read($stream): array
    {
        $json = stream_get_contents($stream);
        if ($json === false) {
            throw new RuntimeException('The initial table fixture could not be read.');
        }
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('The initial table fixture must be JSON.', previous: $error);
        }
        if (!is_array($data)) {
            throw new InvalidArgumentException('An initial table fixture holds named tables.');
        }
        $tables = [];
        $widths = ['topics' => 5, 'categories' => 3, 'keywords' => 2, 'relations' => 2, 'zones' => 3];
        foreach ($data as $name => $rows) {
            if (!is_string($name) || !isset($widths[$name]) || !is_array($rows) || !array_is_list($rows)) {
                throw new InvalidArgumentException('An initial table is a named list of rows.');
            }
            $tables[$name] = [];
            foreach ($rows as $row) {
                if (!is_array($row) || !array_is_list($row) || count($row) !== $widths[$name]) {
                    throw new InvalidArgumentException('An initial table row has the declared number of cells.');
                }
                $cells = [];
                foreach ($row as $cell) {
                    if (!is_string($cell)) {
                        throw new InvalidArgumentException('Initial table fixture cells are strings.');
                    }
                    $cells[] = $cell;
                }
                $tables[$name][] = $cells;
            }
        }

        return $tables;
    }

}
