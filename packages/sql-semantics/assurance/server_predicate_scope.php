<?php

declare(strict_types=1);

/**
 * Supplies the recursive predicate grammar for the two fixture mutation observers.
 */
function fixturePredicateDefinition(string $operand, string $comparison): string
{
    $atom = '(?:\\((?&truth)\\)|' . $operand . '(?:\\h*' . $comparison . '\\h*' . $operand . ')?|TRUE|FALSE|NULL)';
    $factor = '(?:NOT\\h+)*' . $atom . '(?:\\h+IS(?:\\h+NOT)?\\h+NULL)?';

    return '(?(DEFINE)(?<truth>' . $factor . '(?:\\h+(?:AND|OR|XOR)\\h+' . $factor . ')*))';
}
