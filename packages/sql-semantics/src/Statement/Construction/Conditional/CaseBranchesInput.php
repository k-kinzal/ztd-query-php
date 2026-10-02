<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Conditional;

/**
 * Ordered CASE branches and an optional default result.
 * @visibility public
 * @example Specifying new input before name resolution
 *     $input = new \SqlSemantics\Statement\Construction\Conditional\CaseBranchesInput(null, new \SqlSemantics\Statement\Expression\Rendering\SqliteElseLayout(), new \SqlSemantics\Statement\Construction\Conditional\CaseArmInput(new \SqlSemantics\Statement\Expression\NullConstant(), new \SqlSemantics\Statement\Expression\NullConstant()));
 *     count($input->arms) // => 1
 */
final class CaseBranchesInput
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * @var non-empty-list<CaseArmInput>
     */
    public readonly array $arms;

    /**
     * A CASE always has at least one branch; absence of ELSE is retained.
     */
    public function __construct(public readonly ?\SqlSemantics\Statement\Construction\ScalarInput $otherwise, public readonly \SqlSemantics\Statement\Expression\Rendering\SqliteElseLayout $layout, CaseArmInput $first, CaseArmInput ...$rest)
    {
        if ($otherwise !== null) {
            \SqlSemantics\Statement\Construction\InputDomain::check($otherwise);
        }
        $this->arms = [$first, ...array_values($rest)];
    }
}
