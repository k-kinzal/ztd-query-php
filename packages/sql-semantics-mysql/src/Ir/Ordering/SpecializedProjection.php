<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Ordering;

use SqlSemantics\Platform\MySql\Ir\Expression\Choice\SignedCase;
use SqlSemantics\Platform\MySql\Ir\Expression\Choice\SignedCaseLowering;
use SqlSemantics\Platform\MySql\Ir\Expression\CollatedText;
use SqlSemantics\Platform\MySql\Ir\Expression\CollatedTextLowering;
use SqlSemantics\Platform\MySql\Ir\From\JoinBinding;
use SqlSemantics\Platform\MySql\Ir\Predicate\ComparisonLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\MySql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthTest;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison as ParsedComparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\NullTest as ParsedNullTest;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated;
use SqlSemantics\Platform\MySql\Statement\Expression\TruthTest as ParsedTruthTest;

/**
 * Resolves the closed expression roles with specialized projection semantics.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SpecializedProjection
{
    /**
     * Resolves a specialized operand when one of these rules applies.
     */
    public function lower(
        Scalar $operand,
        SourceBinding|JoinBinding $binding,
    ): CollatedText|IntegerComparison|NullTest|SignedCase|TextComparison|TruthTest|null {
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }

        return match (true) {
            $operand instanceof ParsedTruthTest => (new TruthLowering())->lower($operand, $binding),
            $operand instanceof ParsedNullTest => (new PredicateLowering())->nullTest($operand, $binding),
            $operand instanceof CaseExpression => (new SignedCaseLowering())->lower($operand, $binding),
            $operand instanceof ParsedComparison => (new ComparisonLowering())->lower($operand, $binding),
            $operand instanceof Collated => (new CollatedTextLowering())->lower($operand, $binding),
            default => null,
        };
    }
}
