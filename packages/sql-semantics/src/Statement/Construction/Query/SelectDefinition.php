<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Query;

/**
 * All explicit inputs for a new SELECT, without defaults copied from another root.
 * @visibility public
 * @example Specifying new input before name resolution
 *     $input = new \SqlSemantics\Statement\Construction\Query\SelectDefinition(new \SqlSemantics\Statement\Construction\Query\ProjectionDefinition(new \SqlSemantics\Statement\Construction\Query\FieldDefinition(new \SqlSemantics\Statement\Expression\NullConstant())));
 *     $input->where // => null
 */
final class SelectDefinition
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Retains explicit new inputs; no existing scope or bound field is accepted.
     */
    public function __construct(public readonly ProjectionDefinition $projection, public readonly Inputs $from = new Inputs(), public readonly ?\SqlSemantics\Statement\Construction\ScalarInput $where = null, public readonly \SqlSemantics\Statement\Query\Quantifier $quantifier = \SqlSemantics\Statement\Query\Quantifier::Default, public readonly ?LimitDefinition $limit = null)
    {
        if ($where !== null) {
            \SqlSemantics\Statement\Construction\InputDomain::check($where);
        }
    }
}
