<?php

declare(strict_types=1);

namespace Tests\Contract;

use PHPUnit\Framework\Assert;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Statement;
use SqlSemantics\Statement\Traversal;

/**
 * States that a declaration is made of the values of the statement that declares it.
 */
final class Declared
{
    /**
     * Requires every value a declaration of the statement holds to be a value of its command, by identity.
     */
    public static function assertValuesOfTheCommand(Statement $statement): void
    {
        $held = [];
        foreach (Traversal::walk($statement->command) as $value) {
            $held[spl_object_id($value)] = true;
        }
        $declarations = $statement->resolution === null ? [] : $statement->resolution->declarations;
        Assert::assertNotEmpty($declarations);
        foreach ($declarations as $table) {
            $values = [$table->source, ...$table->options];
            foreach ($table->columns as $column) {
                array_push($values, $column->source, $column->defaultExpression, $column->defaultValue, $column->collation, $column->generation?->clause, $column->generation?->expression, ...$column->attributes, ...$column->type->members);
            }
            foreach ($table->constraints as $constraint) {
                array_push($values, $constraint->source, $constraint->expression);
            }
            foreach (array_filter($values, static fn (?Element $value): bool => $value !== null) as $value) {
                Assert::assertArrayHasKey(spl_object_id($value), $held, 'A declared value of ' . $table->name . ' is not a value of the statement: ' . $value::class);
            }
        }
    }
}
