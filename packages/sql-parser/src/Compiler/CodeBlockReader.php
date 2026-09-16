<?php

declare(strict_types=1);

namespace SqlParser\Compiler;

/**
 * Reads a brace-delimited block of host code as one opaque unit.
 *
 * Braces inside string literals, character literals and comments do not
 * count, which is what lets an action such as `{ if (c == '{') ... }` be
 * skipped without being understood.
 *
 * @visibility root
 */
final class CodeBlockReader
{
    /**
     * Consumes the block that opens at the current position.
     *
     * @param SourceReader $reader Reader positioned on the opening brace
     *
     * @return string The block including both braces
     *
     * @throws GrammarSourceException When the block never closes
     */
    public function read(SourceReader $reader): string
    {
        $line = $reader->line();
        $depth = 0;
        $text = '';
        while (!$reader->eof()) {
            $character = $reader->peek();
            if ($character === '{') {
                $depth++;
            } elseif ($character === '}') {
                $depth--;
            }
            $text .= $this->readUnit($reader);
            if ($depth === 0) {
                return $text;
            }
        }

        throw GrammarSourceException::unterminated('code block', $line);
    }

    /**
     * Consumes one lexical unit of host code: a literal, a comment, or a byte.
     *
     * @param SourceReader $reader Reader positioned on the unit
     *
     * @return string The consumed text
     */
    public function readUnit(SourceReader $reader): string
    {
        if ($reader->startsWith('/*')) {
            $start = $reader->offset();
            $reader->skipPast('*/');

            return str_repeat(' ', $reader->offset() - $start);
        }
        if ($reader->startsWith('//')) {
            return $reader->match('[^\n]*') ?? '';
        }
        $character = $reader->peek();
        if ($character === '"' || $character === "'") {
            return $reader->match(preg_quote($character, '/') . '(?:\\\\.|[^\\\\' . $character . '\n])*' . preg_quote($character, '/'))
                ?? $reader->take(1);
        }

        return $reader->take(1);
    }
}
