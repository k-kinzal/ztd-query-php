<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Text;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Evaluation\Function\Strings;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

/**
 * The string functions that encode the bytes or characters of a string: QUOTE, ORD, TO_BASE64 and FROM_BASE64.
 *
 * QUOTE writes the text between single quotes with a backslash before each backslash and
 * single quote, NUL as \0 and Control+Z as \Z, and NULL as the word NULL. ORD answers the bytes
 * of the first character as a big-endian integer. TO_BASE64 encodes the bytes of the text with a
 * newline after each 76 characters; FROM_BASE64 decodes a text whose spaces, tabs, carriage
 * returns and newlines are skipped and whose last group alone is padded, else answers NULL
 * (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_quote,
 * https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_to-base64.
 *
 * @visibility MySqlMemory
 */
final class Encodings
{
    /**
     * The escapes QUOTE writes, by the character escaped.
     */
    public const ESCAPES = ['\\' => '\\\\', "'" => "\\'", "\0" => '\\0', "\x1A" => '\\Z'];

    /**
     * @param Strings $strings Reads the arguments as text of the result
     */
    public function __construct(public readonly Strings $strings = new Strings())
    {
    }

    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('QUOTE', 1, 1, $this->quote(...)),
            new Routine('ORD', 1, 1, $this->ord(...)),
            new Routine('TO_BASE64', 1, 1, $this->toBase64(...)),
            new Routine('FROM_BASE64', 1, 1, $this->fromBase64(...)),
        ];
    }

    /**
     * QUOTE: the text quoted and escaped as a string literal, or the word NULL.
     *
     * @param list<Evaluable> $arguments
     */
    public function quote(Frame $frame, array $arguments, Domain $result): string
    {
        $charset = $result->collation->charset;
        $utf8 = Charset::known('utf8mb4');
        $text = $this->strings->text($frame, $arguments[0], $result);
        if ($text === null) {
            return Encoding::convert('NULL', $utf8, $charset);
        }
        $quote = Encoding::convert("'", $utf8, $charset);
        $quoted = $quote;
        foreach (Encoding::characters($text, $charset) as $character) {
            $escape = self::ESCAPES[$charset === Charset::binary() ? $character : Encoding::convert($character, $charset, $utf8)] ?? null;
            $quoted .= $escape === null ? $character : Encoding::convert($escape, $utf8, $charset);
        }

        return $quoted . $quote;
    }

    /**
     * ORD: the bytes of the first character of the text as an integer, 0 for an empty text.
     *
     * @param list<Evaluable> $arguments
     */
    public function ord(Frame $frame, array $arguments, Domain $result): ?int
    {
        $domain = $arguments[0]->domain();
        $text = Convert::toText($arguments[0]->evaluate($frame), $domain);
        if ($text === null) {
            return null;
        }
        $code = 0;
        foreach (str_split(Encoding::characters($text, $this->strings->charset($domain))[0] ?? '') as $byte) {
            $code = $code << 8 | ord($byte);
        }

        return $code;
    }

    /**
     * TO_BASE64: the base64 encoding of the bytes of the text, a newline after each 76 characters.
     *
     * @param list<Evaluable> $arguments
     */
    public function toBase64(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        if ($text === null) {
            return null;
        }

        return Encoding::convert(rtrim(chunk_split(base64_encode($text), 76, "\n"), "\n"), Charset::known('ascii'), $result->collation->charset);
    }

    /**
     * FROM_BASE64: the bytes a base64 text encodes, or NULL when it is not one.
     *
     * @param list<Evaluable> $arguments
     */
    public function fromBase64(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        if ($text === null) {
            return null;
        }
        $digits = str_replace([' ', "\t", "\r", "\n"], '', $text);
        if (preg_match('~\A(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?\z~', $digits) !== 1) {
            return null;
        }
        $bytes = base64_decode($digits, true);

        return $bytes === false ? null : $bytes;
    }
}
