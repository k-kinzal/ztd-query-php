<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ArgumentText;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * The `internallength` of a base type: a number of bytes, or `variable`.
 *
 * `defGetTypeLength` accepts a 32-bit integer, or `variable` without regard
 * to case written as a string or a word.
 * Source: https://www.postgresql.org/docs/17/sql-createtype.html, `defGetTypeLength` in `src/backend/commands/define.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading a variable length
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TYPE box3 (input = box3_in, output = box3_out, internallength = VARIABLE)');
 *     [$operation->statement->definition[2]->value->bytes(), $operation->statement->definition[2]->value->variable()] // => [null, true]
 * @example Rejecting another word
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\LengthArgument(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('fixed')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class LengthArgument implements AttributeArgument
{
    use Snapshot;

    /**
     * @param SignedNumber|StringConstant|TypeName $length The length as written
     */
    public function __construct(public readonly SignedNumber|StringConstant|TypeName $length)
    {
        $text = new ArgumentText();
        Check::input($length instanceof SignedNumber ? $text->integer($length) !== null : strtolower($text->text($length)) === 'variable', 'A type length is an integer that fits in 32 bits or the word variable.');
    }

    /**
     * Answers the length in bytes, or null for a variable length.
     */
    public function bytes(): ?int
    {
        return $this->length instanceof SignedNumber ? (new ArgumentText())->integer($this->length) : null;
    }

    /**
     * Tells whether the length is written as `variable`.
     */
    public function variable(): bool
    {
        return !$this->length instanceof SignedNumber;
    }

    /**
     * Tells whether the reading reads a type length.
     */
    public function fits(Reading $reading): bool
    {
        return $reading === Reading::Length;
    }

    /**
     * Derives the modifier expressions of a value written as a type name.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->length->deriveClause($derivation, $environment);
    }

    /**
     * Writes the length as written.
     */
    public function render(Output $out): void
    {
        $out->node($this->length);
    }
}
