<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use SqlFormatter\Core\FormatOptions;
use SqlFormatter\Core\FormattingException;
use SqlFormatter\Core\Style;
use SqlFormatter\Facade\Formatter;
use SqlParser\Lexer\SourceException;
use SqlParser\MySql\MySqlParser;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlParser\Sqlite\SqliteParser;

/**
 * Formats arbitrary bytes as SQL text and reports anything but a clean rejection.
 *
 * The first input byte selects the layout preset and the indentation width, the rest is
 * handed to the formatter as the SQL text. Text the parser rejects is not a finding: the
 * parser's own exceptions are the documented answer to invalid input. Everything else is
 * one: a verification failure, any other exception, a fatal error, a timeout, or output
 * that changes when it is formatted again.
 */
final class FormatTarget
{
    /**
     * @param MySqlParser|PostgreSqlParser|SqliteParser $parser The parser of the dialect and release under test
     * @param string $grammarVersion The release tag for findings, e.g. "mysql-8.4.7"
     */
    public function __construct(
        private readonly MySqlParser|PostgreSqlParser|SqliteParser $parser,
        private readonly string $grammarVersion,
    ) {
    }

    /**
     * Derives the layout from the selector byte: two bits choose the preset, four the indentation.
     *
     * @param int $selector The first input byte
     *
     * @return FormatOptions One of the 64 layouts
     */
    public static function options(int $selector): FormatOptions
    {
        $styles = Style::cases();
        return new FormatOptions($styles[$selector % count($styles)], (($selector >> 2) % 16) + 1);
    }

    /**
     * Formats the text and formats the result again.
     *
     * @param string $input The fuzzer input: a selector byte followed by the SQL text
     *
     * @throws Error When formatting fails verification, or when formatting is not idempotent
     */
    public function verify(string $input): void
    {
        if ($input === '') {
            return;
        }
        $options = self::options(ord($input[0]));
        $sql = substr($input, 1);
        $context = "Grammar: {$this->grammarVersion}\nStyle: {$options->style->value}, indent {$options->indentWidth}\nInput (hex): " . bin2hex($input) . "\nSQL: {$sql}";
        $formatter = new Formatter($this->parser, $options);
        try {
            $formatted = $formatter->format($sql);
        } catch (SourceException) {
            return;
        } catch (FormattingException $failure) {
            throw new Error("Formatting failed verification\n{$context}\nError: {$failure->getMessage()}", 0, $failure);
        }
        try {
            $again = $formatter->format($formatted);
        } catch (SourceException|FormattingException $failure) {
            throw new Error("Formatted SQL cannot be formatted again\n{$context}\nFormatted: {$formatted}\nError: {$failure->getMessage()}", 0, $failure);
        }
        if ($again !== $formatted) {
            throw new Error("Formatting is not idempotent\n{$context}\nFirst: {$formatted}\nSecond: {$again}");
        }
    }
}
