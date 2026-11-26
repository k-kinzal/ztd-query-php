<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Expression\Binding;

use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Rendering\PieceKind;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Platform;

/**
 * Reads the lexical registrations of a transient, unevaluated scalar.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class NumberedRegistrations
{
    /**
     * Collects numbered markers in statement order from the checked syntax graph.
     * @return list<Parameter>
     * @throws InvalidConstruction When a marker falls outside the numbered profile
     */
    public function collect(Scalar $expression, string $message): array
    {
        $output = Platform::shared()->syntaxOutput();
        $output->node($expression);
        $bindings = [];
        foreach ($output->canonical() as $piece) {
            if ($piece->kind !== PieceKind::Literal || !str_contains('?:@$', $piece->text[0])) {
                continue;
            }
            Check::input($piece->text[0] === '?', $message);
            $bindings[] = new Parameter(substr($piece->text, 1));
        }

        return $bindings;
    }
}
