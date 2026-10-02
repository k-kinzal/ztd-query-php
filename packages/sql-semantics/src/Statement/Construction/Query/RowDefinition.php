<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Query;

/**
 * A new VALUES row before its column references are resolved.
 * @visibility public
 * @example Specifying new input before name resolution
 *     $input = new \SqlSemantics\Statement\Construction\Query\RowDefinition(new \SqlSemantics\Statement\Expression\NullConstant(), new \SqlSemantics\Statement\Expression\NullConstant());
 *     count($input->expressions) // => 2
 */
final class RowDefinition
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * @var non-empty-list<\SqlSemantics\Statement\Construction\ScalarInput>
     */
    public readonly array $expressions;

    /**
     * Keeps the complete supplied sequence, including duplicate values.
     */
    public function __construct(\SqlSemantics\Statement\Construction\ScalarInput $first, \SqlSemantics\Statement\Construction\ScalarInput ...$rest)
    {
        \SqlSemantics\Statement\Construction\InputDomain::check($first);
        foreach ($rest as $input) {
            \SqlSemantics\Statement\Construction\InputDomain::check($input);
        }
        $this->expressions = [$first, ...array_values($rest)];
    }
}
