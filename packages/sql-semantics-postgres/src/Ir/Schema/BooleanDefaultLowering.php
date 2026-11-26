<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\BooleanInput;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral as ParsedBoolean;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral as ParsedNull;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * Resolves the stock native input of a Boolean default.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class BooleanDefaultLowering
{
    /**
     * Decodes literals in the resolved owning Boolean role.
     *
     * @throws InvalidConstruction When the input has an unsupported type or shape
     */
    public function lower(BoundStatement $operation, Scalar $operand): BooleanLiteral|NullLiteral
    {
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        if ($operand instanceof Cast) {
            $target = $operand->type->typeFact($operation->context);
            Check::input(
                $target instanceof Known && $target->descriptor === Builtin::Bool && !$operand->type->setOf,
                'A Boolean default cast requires pg_catalog.bool.'
            );
            $operand = $operand->operand;
        }
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }

        return match (true) {
            $operand instanceof ParsedBoolean => new BooleanLiteral($operand->value),
            $operand instanceof ParsedNull => new NullLiteral(),
            $operand instanceof Constant && $operand->value instanceof StringConstant => $this->fromText($operand->value->value),
            default => throw new InvalidConstruction('A Boolean default requires a literal Boolean input.'),
        };
    }

    /**
     * Decodes a complete token from the builtin Boolean input language.
     *
     * @throws InvalidConstruction When the text has no unique Boolean meaning
     */
    public function fromText(string $value): BooleanLiteral
    {
        return (new BooleanInput())->fromText($value, 'The Boolean default contains an invalid input token.');
    }
}
