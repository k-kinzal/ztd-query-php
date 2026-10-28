<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Rendering;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * Checks and writes the optional comma between adjacent SQL operands.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class OptionalCommas
{
    /**
     * Checks one comma choice per boundary; an omitted list uses the constructor's default.
     *
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null>|null $commas The unchecked PHP array to validate
     * @return list<bool>
     */
    public function checked(?array $commas, int $items, bool $default = false): array
    {
        $count = max(0, $items - 1);
        if ($commas === null) {
            return array_fill(0, $count, $default);
        }
        Check::input(array_is_list($commas) && count($commas) === $count, 'Optional commas specify exactly the boundaries between list items.');
        $checked = [];
        foreach ($commas as $comma) {
            Check::input(is_bool($comma), 'Each optional comma is a boolean choice.');
            $checked[] = $comma;
        }
        return $checked;
    }

    /**
     * Writes a validated list with its retained boundaries.
     *
     * @param list<Node> $items The operands in order
     * @param list<bool> $commas The boundary choices
     */
    public function render(Output $out, array $items, array $commas): void
    {
        foreach ($items as $position => $item) {
            if ($position > 0 && ($commas[$position - 1] ?? false)) {
                $out->symbol(',');
            }
            $out->node($item);
        }
    }
}
