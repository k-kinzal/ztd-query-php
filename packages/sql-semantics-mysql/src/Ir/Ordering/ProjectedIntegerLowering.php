<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Ordering;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Literal\IntegerTokenLowering;
use SqlSemantics\Platform\MySql\Ir\Literal\IntrinsicIntegerName;
use SqlSemantics\Platform\MySql\Ir\Literal\ProjectedIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\MySql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\Field;
use SqlSemantics\Platform\MySql\Rules\Query\ItemNaming;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

/**
 * Binds the exact numeric width and observable name of a pure integer result.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ProjectedIntegerLowering
{
    /**
     * Publishes a typed integer result when the field is an exact integer token.
     */
    public function lower(Select $statement, Field $field, LanguageProfile $profile): ?ResultColumn
    {
        $expression = $field->expression;
        if ($expression === null) {
            return null;
        }
        $token = (new IntegerTokenLowering())->spelling($expression);
        if ($token === null) {
            return null;
        }
        $literal = new ProjectedIntegerLiteral($token);
        $decimal = $literal->decimal();
        $zeroes = strlen(ltrim($token, '-')) - strlen(ltrim($decimal, '-'));
        $value = $zeroes <= IntegerLiteral::MAX_LEADING_ZEROES && IntegerStorage::Standard->accepts(new RangeIntegerLiteral($decimal))
            ? new IntegerLiteral($decimal, $zeroes, $token[0] === '-' && $decimal === '0') : $literal;
        $name = $this->name($statement, $field, $literal, $profile);

        return new ResultColumn($value, $name);
    }

    /**
     * Selects the native heading of the token or its explicitly supplied alias.
     *
     * @throws InvalidConstruction If the input cannot supply its owned native heading
     */
    public function name(Select $statement, Field $field, ProjectedIntegerLiteral $literal, LanguageProfile $profile): Identifier|IntrinsicIntegerName
    {
        foreach ($statement->items as $item) {
            if (!$item instanceof SelectExpression || $item->expression !== $field->expression) {
                continue;
            }
            if ($item->alias !== null) {
                return (new ProjectionLabel())->lower($statement, $field, $item->alias->value);
            }
            if ($literal->token[0] === '-') {
                $naming = new ItemNaming($profile);
                $stored = $naming->stored($naming->text($item), 'utf8mb4');
                Check::input($stored instanceof Name, 'An integer projection needs a resolved native heading.');

                return new Identifier($stored->value);
            }
            return strlen($literal->token) > 256 ? new IntrinsicIntegerName($literal->token) : new Identifier($literal->token);
        }

        throw new InvalidConstruction('An integer projection needs an owned select item.');
    }
}
