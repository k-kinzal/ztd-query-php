<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Ownership\SequenceOwner;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Ownership\SequenceOwnerLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceAs;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceFlag;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceFlagKind;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceOption;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceReference;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * Resolves one parsed operand to its native sequence parameter role.
 *
 * @visibility SqlSemantics
 */
final class SequenceOptionLowering
{
    /**
     * Interprets the supported option forms without evaluating arbitrary expressions.
     *
     * @return array{string, IntegerStorage|WideIntegerLiteral|SequenceOwner|bool|null}
     * @throws InvalidConstruction When an option has an unresolved or unsupported role
     */
    public function lower(AnalysisContext $context, SequenceOption $option): array
    {
        if ($option instanceof SequenceNumber) {
            $written = $option->value->text();
            Check::input(preg_match('/\A-?[0-9]+\z/D', $written) === 1, 'A sequence option requires a signed decimal integer.');
            $magnitude = ltrim(ltrim($written, '-'), '0');
            $decimal = $magnitude === '' ? '0' : ($written[0] === '-' ? '-' : '') . $magnitude;

            return [$option->kind->value, new WideIntegerLiteral($decimal)];
        }
        if ($option instanceof SequenceAs) {
            $type = $option->type->typeFact($context, false);
            Check::input($type instanceof Known, 'A sequence requires a resolved native integer storage type.');

            return ['AS', match ($type->descriptor) {
                Builtin::Int2 => IntegerStorage::Small,
                Builtin::Int4 => IntegerStorage::Standard,
                Builtin::Int8 => IntegerStorage::Wide,
                default => throw new InvalidConstruction('A sequence requires a resolved native integer storage type.'),
            }];
        }
        if ($option instanceof SequenceFlag) {
            return match ($option->kind) {
                SequenceFlagKind::Cycle => ['CYCLE', true],
                SequenceFlagKind::NoCycle => ['CYCLE', false],
                SequenceFlagKind::NoMinValue => ['MINVALUE', null],
                SequenceFlagKind::NoMaxValue => ['MAXVALUE', null],
                SequenceFlagKind::Restart => ['RESTART', null],
                SequenceFlagKind::Logged, SequenceFlagKind::Unlogged => throw new InvalidConstruction('Sequence creation declares persistence before SEQUENCE.'),
            };
        }
        Check::input($option instanceof SequenceReference && $option->owned, 'Sequence creation requires a checked owner option.');

        return ['OWNED BY', (new SequenceOwnerLowering())->lower($context, $option->name)];
    }
}
