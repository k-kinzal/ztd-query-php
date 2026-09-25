<?php

declare(strict_types=1);

namespace Fuzz\Target;

use RuntimeException;
use SqlSemantics\Binder;
use SqlSemantics\InvalidSql;

/**
 * Reversibility of binding: every generated statement becomes a Statement that writes back the same SQL text.
 */
final class SemanticsTarget
{
    /**
     * Uses the same public binder consumers use.
     */
    public function __construct(public readonly Binder $binder)
    {
    }

    /**
     * Binds the SQL into a Statement and requires toString() to reproduce the SQL exactly.
     *
     * A request the database would reject raises InvalidSql and produces no Statement; every other
     * exception, and every difference between the input and the written SQL, is a failure.
     *
     * @throws RuntimeException
     */
    public function verify(string $sql): void
    {
        try {
            $statement = $this->binder->bind($sql, strict: false);
        } catch (InvalidSql) {
            return;
        }
        $written = $statement->toString();
        if ($written !== $sql) {
            throw new RuntimeException("Statement::toString() does not reproduce the bound SQL.\nInput:  {$sql}\nOutput: {$written}");
        }
    }
}
