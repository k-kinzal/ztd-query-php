<?php

declare(strict_types=1);

namespace SqlParser\Table;

use RuntimeException;
use SqlParser\Grammar\SymbolTable;

/**
 * Writes a parse table to bytes and reads it back.
 *
 * The layout is little-endian throughout: a header of counts, the symbol
 * names, the rules, the default of each state, the fallbacks, the byte
 * offset of each row, and then the rows themselves. A row lists its symbols
 * as 16-bit numbers and then its action codes as 32-bit integers, so a row
 * can be unpacked with two calls.
 *
 * @visibility root
 */
final class TableCodec
{
    /**
     * The bytes every encoded table starts with.
     */
    public const MAGIC = 'SQLPTBL1';

    /**
     * Encodes a table.
     *
     * @param ParseTable $table Table to write
     *
     * @return string The bytes
     */
    public function encode(ParseTable $table): string
    {
        $symbols = $table->symbols;
        $names = '';
        for ($id = 0; $id < $symbols->count(); $id++) {
            $name = $symbols->name($id);
            $names .= pack('v', strlen($name)) . $name;
        }
        $rules = '';
        foreach ($table->rules as $rule) {
            $rules .= pack('VvvC', $rule->lhs, $rule->length, $rule->ordinal, $rule->hidden ? 1 : 0);
        }
        $fallbacks = '';
        foreach ($table->fallbacks as $from => $to) {
            $fallbacks .= pack('vv', $from, $to);
        }
        $rows = '';
        $offsets = [0];
        for ($state = 0; $state < $table->stateCount(); $state++) {
            $rows .= $this->encodeRow($table->rows->row($state));
            $offsets[] = strlen($rows);
        }

        return self::MAGIC
            . pack('VVVVvV', $symbols->terminalCount(), $symbols->count(), count($table->rules), $table->stateCount(), count($table->fallbacks), $table->wildcard ?? 0xFFFFFFFF)
            . $names
            . $rules
            . pack('l*', ...$table->defaults)
            . $fallbacks
            . pack('V*', ...$offsets)
            . $rows;
    }

    /**
     * Unpacks a run of integers.
     *
     * @param string $format Pack format of one repeated integer, such as `v*`
     * @param string $bytes The bytes
     *
     * @return list<int> The integers, none for empty bytes
     *
     * @throws RuntimeException When the bytes are not a whole number of integers
     */
    public static function integers(string $format, string $bytes): array
    {
        $size = $format[0] === 'v' ? 2 : 4;
        if (strlen($bytes) % $size !== 0) {
            throw new RuntimeException('The bytes do not hold whole integers');
        }
        if ($bytes === '') {
            return [];
        }
        $values = unpack($format, $bytes);
        if ($values === false) {
            throw new RuntimeException('The bytes do not hold whole integers');
        }
        /** @var list<int> $integers */
        $integers = array_values($values);

        return $integers;
    }

    /**
     * Encodes the explicit actions of one state.
     *
     * @param array<int, int> $row Action code by symbol number
     *
     * @return string The bytes
     */
    public function encodeRow(array $row): string
    {
        ksort($row);

        return pack('v*', ...array_keys($row)) . pack('l*', ...array_values($row));
    }

    /**
     * Decodes a table.
     *
     * @param string $bytes Bytes as encode() wrote them
     *
     * @return ParseTable The table, with its rows decoded on demand
     *
     * @throws RuntimeException When the bytes are not an encoded table
     */
    public function decode(string $bytes): ParseTable
    {
        if (!str_starts_with($bytes, self::MAGIC)) {
            throw new RuntimeException('The bytes are not an encoded parse table');
        }
        $offset = strlen(self::MAGIC);
        /** @var array{terminals: int, symbols: int, rules: int, states: int, fallbacks: int, wildcard: int} $header */
        $header = unpack('Vterminals/Vsymbols/Vrules/Vstates/vfallbacks/Vwildcard', $bytes, $offset);
        $offset += 22;
        $names = [];
        for ($id = 0; $id < $header['symbols']; $id++) {
            /** @var array{1: int} $length */
            $length = unpack('v', $bytes, $offset);
            $names[] = substr($bytes, $offset + 2, $length[1]);
            $offset += 2 + $length[1];
        }
        $rules = [];
        for ($index = 0; $index < $header['rules']; $index++) {
            /** @var array{lhs: int, length: int, ordinal: int, hidden: int} $rule */
            $rule = unpack('Vlhs/vlength/vordinal/Chidden', $bytes, $offset);
            $rules[] = new TableRule($rule['lhs'], $rule['length'], $rule['ordinal'], $rule['hidden'] === 1);
            $offset += 9;
        }
        $defaults = self::integers('l*', substr($bytes, $offset, $header['states'] * 4));
        $offset += $header['states'] * 4;
        $fallbacks = [];
        for ($index = 0; $index < $header['fallbacks']; $index++) {
            /** @var array{from: int, to: int} $pair */
            $pair = unpack('vfrom/vto', $bytes, $offset);
            $fallbacks[$pair['from']] = $pair['to'];
            $offset += 4;
        }
        $offsets = self::integers('V*', substr($bytes, $offset, ($header['states'] + 1) * 4));
        $offset += ($header['states'] + 1) * 4;

        return new ParseTable(
            new SymbolTable(array_slice($names, 0, $header['terminals']), array_slice($names, $header['terminals'])),
            $rules,
            $defaults,
            new PackedRows(substr($bytes, $offset), $offsets),
            $fallbacks,
            $header['wildcard'] === 0xFFFFFFFF ? null : $header['wildcard'],
        );
    }
}
