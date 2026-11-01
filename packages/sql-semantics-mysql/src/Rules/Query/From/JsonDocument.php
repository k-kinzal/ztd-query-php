<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\From;

use SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;

/**
 * Keeps document constraints at the consuming relation's read boundary.
 * An unused CTE or skipped stored instruction retains its complete operand.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class JsonDocument
{
    /**
     * Finds a fixed non-scalar or subquery condition without opening any input.
     */
    public function problem(JsonTable $table): ?Diagnostic
    {
        if ($table->document instanceof DeferredOperand) {
            return $table->document->problem();
        }
        foreach ((new ReadDependencies())->nodes($table->document) as $node) {
            if ($node instanceof Query) {
                return new Misuse(MisuseRule::JsonTableSubquery);
            }
        }
        return $table->document instanceof Scalar ? null : (new DeferredArguments())->problem([$table->document]);
    }
}
