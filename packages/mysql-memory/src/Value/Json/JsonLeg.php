<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Json;


/**
 * One leg of a JSON path and the values it selects from a value.
 *
 * A member is selected from an object only. A cell is selected from an array, and a value that
 * is no array is taken as an array that holds only it, so that `[0]` and `[last]` select it;
 * `last` is the last cell and `last-N` the cell N before it. A range selects the cells from its
 * first to its last that the array has, none when its first comes after its last. `[*]` selects
 * every cell of an array and `.*` every member of an object; `**` selects a value and every value
 * it holds, each before the values it holds.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json.html ("Searching and Modifying JSON Values").
 *
 * @visibility MySqlMemory
 */
final class JsonLeg
{
    /**
     * @param JsonLegKind $kind The kind of the leg
     * @param string $name The name of the member a member leg selects
     * @param array{bool, int} $from Whether the cell, or the first cell of a range, counts from the last cell, and its distance
     * @param array{bool, int} $to Whether the last cell of a range counts from the last cell, and its distance
     */
    public function __construct(public readonly JsonLegKind $kind, public readonly string $name = '', public readonly array $from = [false, 0], public readonly array $to = [false, 0])
    {
    }

    /**
     * Answers the values the leg selects from a value, in order.
     *
     * @return list<JsonNode>
     */
    public function apply(JsonNode $node): array
    {
        $array = $node->type === JsonKind::Array;
        $cells = $array ? $node->children() : [$node];
        $members = $node->type === JsonKind::Object && is_array($node->value) ? $node->value : [];

        return match ($this->kind) {
            JsonLegKind::Member => isset($members[$this->name]) ? [$members[$this->name]] : [],
            JsonLegKind::AnyMember => $node->type === JsonKind::Object ? $node->children() : [],
            JsonLegKind::Cell => array_filter([$cells[$this->cell($this->from, count($cells))] ?? null]),
            JsonLegKind::AnyCell => $array ? $cells : [],
            JsonLegKind::Range => array_slice($cells, max(0, $this->cell($this->from, count($cells))), max(0, min($this->cell($this->to, count($cells)), count($cells) - 1) - max(0, $this->cell($this->from, count($cells))) + 1)),
            JsonLegKind::Descendants => $node->descendants(),
        };
    }

    /**
     * Answers the position a cell index names in an array of a size: from the first cell, or back from the last.
     *
     * @param array{bool, int} $index
     *
     * @example The cell before the last of three
     *     (new \MySqlMemory\Value\Json\JsonLeg(\MySqlMemory\Value\Json\JsonLegKind::Cell))->cell([true, 1], 3) // => 1
     */
    public function cell(array $index, int $size): int
    {
        return $index[0] ? $size - 1 - $index[1] : $index[1];
    }
}
