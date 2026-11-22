<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Aggregation;

use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;

/**
 * Resolves simple grouped operands and their effective SQLite collations.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class GroupBinding
{
    /**
     * Reads the bound fields of the current analysis.
     */
    public function __construct(private readonly BoundStatement $operation, private readonly SourceBinding $source)
    {
    }

    /**
     * Resolves a field and its explicit or declared collation.
     */
    public function key(Scalar $expression, bool $compareText = true): GroupKey
    {
        $explicit = null;
        while ($expression instanceof Grouped || $expression instanceof Collate) {
            if ($expression instanceof Collate) {
                Check::input($explicit === null, 'A grouped field can have one explicit collation.');
                $explicit = $expression->collation;
            }
            $expression = $expression->operand;
        }
        $reference = $this->source->reference($expression);
        $resolution = $this->operation->facts->scalar($expression)->resolution;
        Check::invariant($resolution instanceof ResolvedColumn, 'The bound grouping slot is a resolved column.');
        $default = $compareText ? $resolution->declaration()?->collation : null;
        $collation = Collation::tryFrom(strtoupper(($explicit ?? $default)->value ?? 'BINARY'));
        Check::input($collation !== null, 'A grouped text comparison requires a resolved builtin collation.');

        return new GroupKey($reference, $collation);
    }
}
