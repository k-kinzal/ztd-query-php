<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Derives and writes the window written after OVER: a window name or a window specification.
 *
 * Rule: MYSQL-WINDOWING-001. A window name refers to a window of the WINDOW
 * clause of the same query block, which the query family resolves; a
 * specification is derived at the position of the call. Terminates: the
 * specification is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-functions-usage.html,
 * https://dev.mysql.com/doc/refman/8.4/en/window-functions-named-windows.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Windows
{
    /**
     * Derives the expressions of a window specification.
     */
    public function derive(Name|WindowSpecification|null $over, Derivation $derivation, Environment $environment): void
    {
        if ($over instanceof WindowSpecification) {
            $over->deriveWindow($derivation, $environment);
        }
    }

    /**
     * Writes OVER and the window, when there is one.
     */
    public function render(Name|WindowSpecification|null $over, Output $out): void
    {
        if ($over instanceof Name) {
            $out->keyword('OVER')->name($over, NameUse::Label);
        } elseif ($over !== null) {
            $out->keyword('OVER')->node($over);
        }
    }
}
