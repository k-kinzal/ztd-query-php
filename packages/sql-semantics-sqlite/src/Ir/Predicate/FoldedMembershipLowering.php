<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Predicate;

use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Rendering\PieceKind;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Rendering\Codec;

/**
 * Reads the lexical registrations of the discarded operand before publishing its decision.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class FoldedMembershipLowering
{
    /**
     * Preserves marker order without resolving the discarded operand.
     * @throws InvalidConstruction When a discarded marker is outside the numbered profile
     */
    public function lower(Scalar $expression, bool $matches): FoldedMembership
    {
        $output = new Output(new Codec());
        $output->node($expression);
        $bindings = [];
        foreach ($output->canonical() as $piece) {
            if ($piece->kind !== PieceKind::Literal || !str_contains('?:@$', $piece->text[0])) {
                continue;
            }
            Check::input($piece->text[0] === '?', 'An empty membership requires numbered bindings.');
            $bindings[] = new Parameter(substr($piece->text, 1));
        }

        return new FoldedMembership($matches, $bindings);
    }
}
