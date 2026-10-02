<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Conditional;

/**
 * One WHEN condition or matching value, paired with its THEN result.
 * @visibility public
 * @example Specifying new input before name resolution
 *     $input = new \SqlSemantics\Statement\Construction\Conditional\CaseArmInput(new \SqlSemantics\Statement\Expression\NullConstant(), new \SqlSemantics\Statement\Expression\NullConstant());
 *     $input->then instanceof \SqlSemantics\Statement\Expression\NullConstant // => true
 */
final class CaseArmInput
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Specifies a new evaluation structure without an existing expression graph.
     */
    public function __construct(public readonly \SqlSemantics\Statement\Construction\ScalarInput $when, public readonly \SqlSemantics\Statement\Construction\ScalarInput $then, public readonly \SqlSemantics\Statement\Expression\Rendering\SqliteCaseArmLayout $layout = new \SqlSemantics\Statement\Expression\Rendering\SqliteCaseArmLayout())
    {
        \SqlSemantics\Statement\Construction\InputDomain::check($when);
        \SqlSemantics\Statement\Construction\InputDomain::check($then);
    }
}
