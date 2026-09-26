<?php

declare(strict_types=1);

namespace Requirements\Ears;

use Requirements\Input\InvalidInputException;

/**
 * Validates the clause grammar published at https://alistairmavin.com/ears/.
 *
 * A statement is an optional feature (Where), preconditions (While), at most one trigger
 * (When or If) and one system response. Quoted and code literals are masked first, so
 * keywords inside them do not count as clauses.
 */
final class Validator
{
    /**
     * Checks that a statement follows the EARS clause grammar.
     *
     * @param string $statement The specification statement
     *
     * @throws InvalidInputException When the statement breaks the grammar; the message starts with "EARS:"
     */
    public function validate(string $statement): void
    {
        $text = (new LiteralMask())->apply(trim($statement));
        $clauses = preg_split('/,\s*(?=(?:where|while|when|if|then\s+the|the)\b)/iu', $text);
        if ($clauses === false || $clauses === []) {
            throw new InvalidInputException('EARS: cannot read clauses.');
        }
        $main = array_pop($clauses);
        $order = new ConditionOrder();
        foreach ($clauses as $clause) {
            $order->accept($clause);
        }
        (new SystemResponse())->validate($main, $order->trigger());
    }
}
