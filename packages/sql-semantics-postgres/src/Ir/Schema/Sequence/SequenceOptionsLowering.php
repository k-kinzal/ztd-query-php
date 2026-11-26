<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Ownership\SequenceOwner;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceOption;

/**
 * Collects exact sequence option roles before applying the generator defaults.
 *
 * @visibility SqlSemantics
 */
final class SequenceOptionsLowering
{
    /**
     * Rejects repeated choices, including numeric and operand-free forms of one option.
     *
     * @param list<SequenceOption> $options
     * @return array{SequenceParameters, ?SequenceOwner}
     */
    public function lower(AnalysisContext $context, array $options): array
    {
        $read = new SequenceOptionLowering();
        $seen = [];
        $numbers = [];
        $type = IntegerStorage::Wide;
        $cycle = false;
        $owner = null;
        foreach ($options as $option) {
            [$key, $value] = $read->lower($context, $option);
            Check::input(!isset($seen[$key]), 'A sequence option may be specified only once.');
            $seen[$key] = true;
            match (true) {
                $value instanceof IntegerStorage => $type = $value,
                $value instanceof WideIntegerLiteral => $numbers[$key] = $value,
                $value instanceof SequenceOwner => $owner = $value,
                is_bool($value) => $cycle = $value,
                $value === null => null,
            };
        }

        return [new SequenceParameters(
            $type,
            $numbers['INCREMENT'] ?? new WideIntegerLiteral('1'),
            $numbers['MINVALUE'] ?? null,
            $numbers['MAXVALUE'] ?? null,
            $numbers['START'] ?? null,
            $numbers['RESTART'] ?? null,
            $numbers['CACHE'] ?? new WideIntegerLiteral('1'),
            $cycle,
        ), $owner];
    }
}
