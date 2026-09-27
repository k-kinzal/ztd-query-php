<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

/**
 * A complete SQL command whose values are independent of parsing.
 *
 * Fixed syntax is defined by the concrete value classes. Arguments are named
 * fields, finite options are enums, and forwarding grammar rules are removed.
 * No source string, parser node, token, or construction map is retained.
 * Comments written before the command or after it belong to the statement,
 * so they survive when the command is replaced.
 *
 * @visibility public
 * @example Reconstructing a statement from its values
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1');
 *     str_contains($statement->toString(), 'SELECT') // => true
 * @example Keeping a trailing comment while replacing the command
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 -- audited');
 *     $statement->withCommand($statement->command)->toString() // => "SELECT 1 -- audited"
 */
final class Statement
{
    use Assertion;

    /**
     * The comment position before the command.
     */
    public const BEFORE = 0;

    /**
     * The comment position after the command.
     */
    public const AFTER = 1;

    /**
     * Supplies the complete command value and the comments written around it.
     *
     * @param Comments $comments Comments at position BEFORE precede the command; those at AFTER follow it
     */
    public function __construct(public readonly Command $command, public readonly Comments $comments = new Comments())
    {
        $this->assertImmutableValueGraph($command);
        $this->assert(array_diff($comments->positions(), [self::BEFORE, self::AFTER]) === [], 'Statement comments are written before or after the command.');
    }

    /**
     * Returns a statement containing the replacement command, preserving this statement.
     */
    public function withCommand(Command $command): self
    {
        return new self($command, $this->comments);
    }

    /**
     * Returns a statement with other comments around the same command.
     */
    public function withComments(Comments $comments): self
    {
        return new self($this->command, $comments);
    }

    /**
     * Reconstructs SQL solely from the command's fields, options, and comments.
     */
    public function toString(): string
    {
        $writer = new Writer();
        $writer->comments($this->comments, self::BEFORE);
        $this->command->write($writer);
        $writer->comments($this->comments, self::AFTER);

        return $writer->toString();
    }
}
