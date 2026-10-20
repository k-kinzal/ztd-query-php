<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem\Location;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Locator;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Statement\Fact\Diagnostic;

/**
 * Places conditions using the semantic operand or row that owns them.
 *
 * A VALUES row's width is checked before its elements; a conversion type is
 * checked after its operand. Neither case needs a source range or SQL text.
 *
 * @visibility MySqlMemory
 */
final class Owned
{
    /**
     * Adds each condition at the resolution position of its semantic owner.
     *
     * @param list<Diagnostic> $diagnostics
     * @param array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}> $located
     * @return array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}>
     */
    public function located(Locator $locator, array $diagnostics, array $located): array
    {
        foreach ($diagnostics as $diagnostic) {
            $owner = match (true) {
                $diagnostic instanceof TimeZoneConversion, $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidJsonDocument, $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidJsonPath => $diagnostic->expression,
                $diagnostic instanceof Misuse, $diagnostic instanceof CountMismatch => $diagnostic->valueRow,
                default => null,
            };
            $place = $owner === null ? null : $locator->place($owner);
            if ($place !== null) {
                if ($diagnostic instanceof TimeZoneConversion || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidJsonDocument || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidJsonPath) {
                    $place[1][] = PHP_INT_MAX;
                }
                $located[spl_object_id($diagnostic)] = [$diagnostic, $place];
            }
        }

        return $located;
    }
}
