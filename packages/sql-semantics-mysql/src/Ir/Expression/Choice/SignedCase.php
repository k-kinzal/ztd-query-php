<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Expression\Choice;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * An ordered MySQL signed CASE with one checked comparison form.
 *
 * @visibility public
 * @example Preserving a searched CASE without an ELSE
 *     $null = new \SqlSemantics\Platform\MySql\Ir\NullLiteral();
 *     $arm = new \SqlSemantics\Platform\MySql\Ir\Expression\Choice\SearchedCaseArm($null, $null);
 *     (new \SqlSemantics\Platform\MySql\Ir\Expression\Choice\SignedCase([$arm]))->otherwise // => null
 */
final class SignedCase
{
    use Snapshot;

    /**
     * @var non-empty-list<SimpleCaseArm|SearchedCaseArm>
     */
    public readonly array $arms;

    /**
     * Checks the decision form and detaches the ordered alternatives from the caller.
     * @param array<array-key, mixed> $arms
     */
    public function __construct(
        array $arms,
        public readonly BooleanLiteral|ColumnReference|IntegralColumnReference|NullLiteral|RangeIntegerLiteral|null $subject = null,
        public readonly BooleanLiteral|ColumnReference|IntegralColumnReference|NullLiteral|RangeIntegerLiteral|self|null $otherwise = null,
    ) {
        Check::input(array_is_list($arms) && $arms !== [] && count($arms) <= 32, 'A signed CASE requires one to 32 ordered arms.');
        $owned = [];
        foreach ($arms as $arm) {
            Check::input(
                $subject === null ? $arm instanceof SearchedCaseArm : $arm instanceof SimpleCaseArm,
                'A signed CASE arm must match the selected decision form.',
            );
            $owned[] = $arm;
        }
        $this->arms = $owned;
    }

    /**
     * Returns every child in evaluation and output order for scope checking.
     * @return list<BooleanLiteral|ColumnReference|IntegralColumnReference|NullLiteral|RangeIntegerLiteral|IntegerMembership|self>
     */
    public function children(): array
    {
        $children = $this->subject === null ? [] : [$this->subject];
        foreach ($this->arms as $arm) {
            $children[] = $arm->decision;
            $children[] = $arm->result;
        }
        if ($this->otherwise !== null) {
            $children[] = $this->otherwise;
        }

        return $children;
    }
}
