<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Expression;

/**
 * An ordered membership request, including an empty list.
 * @visibility public
 * @example Specifying new input before name resolution
 *     $input = new \SqlSemantics\Statement\Construction\Expression\InListInput(new \SqlSemantics\Statement\Expression\NullConstant(), false, new \SqlSemantics\Statement\Expression\NullConstant(), new \SqlSemantics\Statement\Expression\NullConstant());
 *     count($input->choices) // => 2
 */
final class InListInput implements \SqlSemantics\Statement\Construction\ScalarInput
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * @var list<\SqlSemantics\Statement\Construction\ScalarInput>
     */
    public readonly array $choices;

    /**
     * Keeps duplicate inputs and their evaluation order.
     */
    public function __construct(public readonly \SqlSemantics\Statement\Construction\ScalarInput $subject, public readonly bool $negated = false, \SqlSemantics\Statement\Construction\ScalarInput ...$choices)
    {
        \SqlSemantics\Statement\Construction\InputDomain::check($subject);
        foreach ($choices as $input) {
            \SqlSemantics\Statement\Construction\InputDomain::check($input);
        }
        $this->choices = array_values($choices);
    }
}
