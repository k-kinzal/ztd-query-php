<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Ownership\SequenceOwner;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceOptionLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceOption;

/**
 * Resolves one assignment for each native option role.
 * @visibility SqlSemantics
 */
final class SequenceChangesLowering
{
    /**
     * Distinguishes omitted options from native defaults and explicit values.
     *
     * @param list<SequenceOption> $options
     * @throws InvalidConstruction When a reset has no supported assignment role
     */
    public function lower(AnalysisContext $context, array $options): SequenceChanges
    {
        $reader = new SequenceOptionLowering();
        $values = [];
        foreach ($options as $option) {
            [$key, $operand] = $reader->lower($context, $option);
            Check::input(!isset($values[$key]), 'A sequence option may be specified only once.');
            $values[$key] = $operand ?? match ($key) {
                'MINVALUE', 'MAXVALUE', 'RESTART' => SequenceResetOperand::Native,
                'OWNED BY' => ClearSequenceOwner::Remove,
                default => throw new InvalidConstruction('A sequence reset requires a supported assignment role.'),
            };
        }

        /** @var array{AS?: IntegerStorage, INCREMENT?: WideIntegerLiteral, MINVALUE?: WideIntegerLiteral|SequenceResetOperand,
         *     MAXVALUE?: WideIntegerLiteral|SequenceResetOperand, START?: WideIntegerLiteral, RESTART?: WideIntegerLiteral|SequenceResetOperand,
         *     CACHE?: WideIntegerLiteral, CYCLE?: bool, 'OWNED BY'?: SequenceOwner|ClearSequenceOwner} $values
         */
        return new SequenceChanges(
            $values['AS'] ?? null,
            $values['INCREMENT'] ?? null,
            $values['MINVALUE'] ?? null,
            $values['MAXVALUE'] ?? null,
            $values['START'] ?? null,
            $values['RESTART'] ?? null,
            $values['CACHE'] ?? null,
            $values['CYCLE'] ?? null,
            $values['OWNED BY'] ?? null,
        );
    }
}
