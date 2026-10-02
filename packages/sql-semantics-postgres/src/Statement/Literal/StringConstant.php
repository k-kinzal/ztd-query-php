<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * The value of a string constant, whichever way it was quoted.
 *
 * `'...'`, `E'...'`, `U&'...'` and dollar quoting are spellings of the same
 * thing: the decoded text. Segments continued on a following line are one
 * value. The value is written back as a plain quoted constant.
 * Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-STRINGS.
 *
 * @visibility public
 * @example Reading the value of an escape string
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SELECT E'a\\tb'");
 *     $query->statement->targets[0]->expression->value->value // => "a\tb"
 * @example Rejecting a value the server cannot hold
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant("a\0b") // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class StringConstant implements OptionArgument
{
    use Snapshot;

    /**
     * @param string $value The decoded text
     */
    public function __construct(public readonly string $value)
    {
        Check::input(!str_contains($value, "\0"), 'A PostgreSQL string constant holds no zero byte.');
    }

    /**
     * Derives nothing: a constant holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the value as a quoted constant.
     */
    public function render(Output $out): void
    {
        $out->spelled((new Spelling())->string($this->value));
    }
}
