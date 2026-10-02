<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Conditional;

/**
 * A CASE whose base input is evaluated once for its comparisons.
 * @visibility public
 * @example Specifying new input before name resolution
 *     $input = new \SqlSemantics\Statement\Construction\Conditional\SimpleCaseInput(new \SqlSemantics\Statement\Expression\NullConstant(), new \SqlSemantics\Statement\Construction\Conditional\CaseBranchesInput(null, new \SqlSemantics\Statement\Expression\Rendering\SqliteElseLayout(), new \SqlSemantics\Statement\Construction\Conditional\CaseArmInput(new \SqlSemantics\Statement\Expression\NullConstant(), new \SqlSemantics\Statement\Expression\NullConstant())));
 *     count($input->branches->arms) // => 1
 */
final class SimpleCaseInput implements \SqlSemantics\Statement\Construction\ScalarInput
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Specifies a new evaluation structure without an existing expression graph.
     */
    public function __construct(public readonly \SqlSemantics\Statement\Construction\ScalarInput $base, public readonly CaseBranchesInput $branches, public readonly \SqlSemantics\Statement\Expression\Rendering\SqliteCaseLayout $layout = new \SqlSemantics\Statement\Expression\Rendering\SqliteCaseLayout())
    {
        \SqlSemantics\Statement\Construction\InputDomain::check($base);
    }
}
