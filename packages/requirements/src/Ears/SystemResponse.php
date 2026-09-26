<?php

declare(strict_types=1);

namespace Requirements\Ears;

use Requirements\Input\InvalidInputException;

/**
 * Checks the system clause that ends every EARS statement.
 *
 * The clause reads "The <system name> shall <system response>", or "Then the ..." after an
 * If trigger, and contains a single shall.
 */
final class SystemResponse
{
    /**
     * Checks the system clause.
     *
     * @param string $clause The last clause of the statement
     * @param string|null $trigger The trigger keyword of the statement, if any
     *
     * @throws InvalidInputException When the clause does not name a system and a response, or repeats shall
     */
    public function validate(string $clause, ?string $trigger): void
    {
        $prefix = $trigger === 'if' ? 'then\s+the' : 'the';
        if (preg_match('/^' . $prefix . '\s+(.+?)\s+shall\s+(.+)$/isuD', trim($clause), $match) !== 1 || !Wording::hasContent($match[1]) || !Wording::hasContent($match[2])) {
            throw new InvalidInputException('EARS: expected ' . ($trigger === 'if' ? 'Then the' : 'The') . ' <system name> shall <system response>.');
        }
        if (preg_match('/\bshall\b/iu', $match[2]) === 1) {
            throw new InvalidInputException('EARS: use one system clause; combine responses after its shall.');
        }
    }
}
