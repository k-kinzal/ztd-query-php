<?php

declare(strict_types=1);

namespace SqlParser\Table;

/**
 * Encodes the optional unranked-conflict extension after the original parse-table rows.
 *
 * @visibility root
 */
final class AlternativeCodec
{
    /**
     * @param array<int, array<int, list<int>>> $alternatives Action alternatives by state and terminal
     * @return string Little-endian integer records; empty for deterministic tables
     */
    public function encode(array $alternatives): string
    {
        $bytes = '';
        foreach ($alternatives as $state => $terminals) {
            foreach ($terminals as $terminal => $actions) {
                $bytes .= pack('V*', $state, $terminal, count($actions)) . pack('l*', ...$actions);
            }
        }
        return $bytes;
    }

    /**
     * @return array<int, array<int, list<int>>> Alternatives retained by the table generator
     */
    public function decode(string $bytes): array
    {
        $integers = TableCodec::integers('l*', $bytes);
        $alternatives = [];
        for ($index = 0; $index < count($integers);) {
            $state = $integers[$index++];
            $terminal = $integers[$index++];
            $length = $integers[$index++];
            $alternatives[$state][$terminal] = array_slice($integers, $index, $length);
            $index += $length;
        }
        return $alternatives;
    }
}
