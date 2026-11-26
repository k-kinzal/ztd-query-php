<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ArgumentText;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;

/**
 * The `internallength` of a base type: a number of bytes, or `variable`.
 *
 * `defGetTypeLength` accepts a 32-bit integer, or `variable` without regard
 * to case written as a string or a word.
 * Source: https://www.postgresql.org/docs/17/sql-createtype.html, `defGetTypeLength` in `src/backend/commands/define.c` of PostgreSQL 17.
 *
 * @visibility SqlSemantics
 * @example Rejecting another word
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\LengthArgument(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('fixed')) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
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
