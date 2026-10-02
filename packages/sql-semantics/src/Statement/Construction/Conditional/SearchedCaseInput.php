<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Conditional;

/**
 * A CASE whose WHEN inputs are evaluated as predicates in sequence.
 * @visibility public
 * @example Specifying new input before name resolution
 *     $input = new \SqlSemantics\Statement\Construction\Conditional\SearchedCaseInput(new \SqlSemantics\Statement\Construction\Conditional\CaseBranchesInput(null, new \SqlSemantics\Statement\Construction\Conditional\CaseArmInput(new \SqlSemantics\Statement\Expression\NullConstant(), new \SqlSemantics\Statement\Expression\NullConstant())));
 *     $input->branches->otherwise // => null
 */
final class SearchedCaseInput implements \SqlSemantics\Statement\Construction\ScalarInput
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Specifies a new evaluation structure without an existing expression graph.
     */
    public function __construct(public readonly CaseBranchesInput $branches)
    {
    }
}
