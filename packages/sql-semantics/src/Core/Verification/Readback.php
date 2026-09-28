<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Verification;

use LogicException;
use SqlParser\Lexer\SourceException;
use SqlSemantics\Core\Language;
use SqlSemantics\Statement\Command;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Equality;
use SqlSemantics\Statement\Statement;
use SqlSemantics\Statement\StatementException;
use SqlSemantics\Statement\Writer;

/**
 * Reads the SQL a statement writes back in its language, and requires the same command and comments.
 *
 * This is the one condition that makes a statement valid SQL: the parser of
 * the release, under the mode, reads the text into the values the statement
 * holds. A reserved word written as a name, an operand that binds too weakly,
 * a comment that swallows the SQL after it, and a form of another release all
 * fail it, without a rule for each.
 *
 * @visibility SqlSemantics
 */
final class Readback
{
    /**
     * Reads back in one language.
     */
    public function __construct(private readonly Language $language)
    {
    }

    /**
     * Requires the SQL the statement writes to be read back as the same command and comments.
     *
     * @throws StatementException When the SQL does not parse in the language, or parses as other SQL
     * @throws LogicException When parser and model resources disagree
     */
    public function check(Statement $statement): void
    {
        $sql = $statement->toString();
        try {
            [$command, $comments] = $this->language->values()->command($this->language->parser()->parse($sql));
        } catch (SourceException $error) {
            throw new StatementException('The statement is not SQL of ' . $this->language->version . ': ' . $error->getMessage() . "\nSQL: " . $sql, 0, $error);
        }
        if (!$this->encloses($command, $statement->command) || !$comments->equals($statement->comments)) {
            throw new StatementException('The statement is read back as other SQL in ' . $this->language->version . "; parenthesize an operand, quote a name, or use a form of the release.\nSQL: " . $sql);
        }
    }

    /**
     * Answers whether the value read back is the command, or encloses it in forms that write nothing more.
     *
     * The grammar reads a statement into its start form, which ends it with
     * an optional terminator; a command built without that envelope writes
     * the same SQL and is the value the envelope holds.
     */
    public function encloses(Element $read, Command $command): bool
    {
        while (!Equality::same($read, $command)) {
            $written = array_values(array_filter($read->children(), static fn (Element $child): bool => Writer::render($child) !== ''));
            if (count($written) !== 1 || Writer::render($read) !== Writer::render($written[0])) {
                return false;
            }
            $read = $written[0];
        }

        return true;
    }
}
