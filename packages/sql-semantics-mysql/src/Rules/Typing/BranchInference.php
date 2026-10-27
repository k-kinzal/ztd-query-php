<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariableBinding;
use SqlSemantics\Statement\Scalar;

/**
 * Infers absent variable operands of CASE and control functions before their result types are aggregated.
 *
 * From MySQL 8.0 the first other resolved branch supplies the operand type, even when
 * it is a literal NULL. Inference does not allocate a variable entry or change the
 * type of another occurrence. Verified through SQL on live MySQL servers.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class BranchInference
{
    /**
     * Resolves contextual operands. Legacy NULL markers contribute empty binary
     * strings in CASE, IFNULL and COALESCE, while IF ignores their NULL type.
     * Literal NULL branches remain ignorable. Verified through prepared metadata
     * on MySQL 5.6.51 and 5.7.44, including numeric and multibyte branches.
     *
     * @param list<Domain> $domains The result branch types
     * @param list<Scalar|null> $nodes The corresponding expressions, when available
     * @return list<Domain>
     */
    public function resolve(array $domains, array $nodes, Derivation $derivation, string $operation = 'case'): array
    {
        $legacy = in_array($derivation->context->profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $facts = $derivation->facts();
        $absent = [];
        foreach (array_keys($domains) as $position) {
            $node = $nodes[$position] ?? null;
            while ($node instanceof Grouped) {
                $node = $node->operand;
            }
            if ($legacy) {
                if ($operation !== 'IF' && $node instanceof Parameter && $domains[$position]->kind === Kind::Null) {
                    $domains[$position] = new Domain(Kind::String, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::VarString, 0, 0, false, Collation::binary(), [], Coercibility::Coercible);
                }
                continue;
            }
            $binding = $node !== null && $node->hasBinding() ? $node->scalarInformation()->resolution : null;
            if ($binding instanceof UserVariableBinding && !$binding->exists) {
                $absent[] = $position;
            }
        }
        $known = array_values(array_diff_key($domains, array_flip($absent)));
        $first = $absent === [] ? null : ($known[0] ?? null);
        if ($first !== null) {
            $inferred = (new Variables(Settings::of($derivation->context), $derivation->context->profile->grammar))->inferred($first);
            foreach ($absent as $position) {
                $domains[$position] = $inferred;
            }
        }

        return array_values($domains);
    }
}
