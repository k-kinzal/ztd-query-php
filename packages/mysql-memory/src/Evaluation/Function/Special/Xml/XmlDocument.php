<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Special\Xml;

/**
 * An XML fragment as EXTRACTVALUE and UPDATEXML read it: its elements, attributes and texts in document order.
 *
 * Node 0 is the root, which holds the top-level elements and texts. An attribute holds its value
 * as a text. The reader is lenient as the server's: a closing tag closes the open element
 * whatever its name, text may stand at the top level, comments, processing instructions and
 * declarations are skipped, an attribute without a value is dropped, entities are kept as written
 * and CDATA is a text. A fragment that does not read is a message naming the line and the column
 * of the problem, as the server words it. Each element and attribute keeps the span of its
 * markup, which UPDATEXML replaces; the span of an element with a closing tag ends one character
 * after the closing name (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xml-functions.html.
 *
 * @visibility MySqlMemory
 */
final class XmlDocument
{
    /**
     * The kind of the root node.
     */
    public const ROOT = 0;

    /**
     * The kind of an element.
     */
    public const ELEMENT = 1;

    /**
     * The kind of an attribute.
     */
    public const ATTRIBUTE = 2;

    /**
     * The kind of a text.
     */
    public const TEXT = 3;

    /**
     * The nodes in document order: kind, name or text, parent, start and end of the span.
     *
     * @var list<array{int, string, int, int, int}>
     */
    public array $nodes = [[self::ROOT, '', -1, 0, 0]];

    /**
     * The children of each node in document order, by node; an element lists its attributes first.
     *
     * @var array<int, list<int>>
     */
    public array $children = [0 => []];

    /**
     * The position of the reader in the text.
     */
    public int $at = 0;

    /**
     * @param string $text The XML text
     */
    public function __construct(public readonly string $text)
    {
    }

    /**
     * Reads a fragment, or answers the message of the first problem.
     */
    public static function read(string $text): self|string
    {
        $document = new self($text);
        $problem = $document->parse();
        $document->nodes[0][4] = strlen($text);

        return $problem ?? $document;
    }

    /**
     * Reads the whole text into nodes, or answers the message of the first problem.
     */
    public function parse(): ?string
    {
        $length = strlen($this->text);
        $open = [0];
        while (true) {
            if ($this->at >= $length) {
                return count($open) > 1 ? $this->problem('unexpected END-OF-INPUT') : null;
            }
            $parent = $open[count($open) - 1];
            if ($this->text[$this->at] !== '<') {
                $end = strpos($this->text, '<', $this->at);
                $end = $end === false ? $length : $end;
                $this->add(self::TEXT, substr($this->text, $this->at, $end - $this->at), $parent, $this->at, $end);
                $this->at = $end;
                continue;
            }
            if (substr_compare($this->text, '<!--', $this->at, 4) === 0) {
                $end = strpos($this->text, '-->', $this->at + 4);
                $this->at = $end === false ? $length : $end + 3;
                continue;
            }
            if (substr_compare($this->text, '<![CDATA[', $this->at, 9) === 0) {
                $end = strpos($this->text, ']]>', $this->at + 9);
                $this->add(self::TEXT, substr($this->text, $this->at + 9, ($end === false ? $length : $end) - $this->at - 9), $parent, $this->at, $end === false ? $length : $end + 3);
                $this->at = $end === false ? $length : $end + 3;
                continue;
            }
            $problem = $this->tag($open);
            if ($problem !== null) {
                return $problem;
            }
        }
    }

    /**
     * Reads one tag at the reader, opening or closing an element, or answers the message of its problem.
     *
     * @param list<int> $open The open elements, the root first
     */
    public function tag(array &$open): ?string
    {
        $start = $this->at;
        $this->at++;
        [$token, $text] = $this->token();
        if ($token === '/') {
            return $this->close($open);
        }
        $mark = $token === '?' || $token === '!' ? $token : '';
        if ($mark !== '') {
            [$token, $text] = $this->token();
        }
        if ($token !== 'IDENT') {
            return $this->unexpected($token, $text, "ident or '/'");
        }
        $element = $mark === '' ? $this->add(self::ELEMENT, $text, $open[count($open) - 1], $start, $start) : -1;
        $after = $this->attributes($element);
        if (is_string($after)) {
            return $after;
        }

        return $this->end($mark, $after[0], $after[1], $element, $open);
    }

    /**
     * Reads the rest of a closing tag after its `/`, closing the open element whatever its name, or answers the message of its problem.
     *
     * @param list<int> $open The open elements, the root first
     */
    public function close(array &$open): ?string
    {
        [$token, $name] = $this->token();
        if ($token !== 'IDENT') {
            return $this->unexpected($token, $name, 'ident');
        }
        if (count($open) === 1) {
            return $this->problem("'</" . $name . ">' unexpected (END-OF-INPUT wanted)");
        }
        $end = $this->at + 1;
        [$token, $text] = $this->token();
        if ($token !== '>') {
            return $this->unexpected($token, $text, "'>'");
        }
        $element = array_pop($open) ?? 0;
        $this->nodes[$element][4] = min($end, strlen($this->text));

        return null;
    }

    /**
     * Reads the attributes of a tag, adding them to its element unless it is -1, and answers the token after them or the message of a problem.
     *
     * @return array{string, string}|string
     */
    public function attributes(int $element): array|string
    {
        [$token, $text] = $this->token();
        while ($token === 'IDENT') {
            $name = $text;
            $from = $this->at - strlen($name);
            [$token, $text] = $this->token();
            if ($token !== '=') {
                continue;
            }
            [$token, $value] = $this->token();
            if ($token !== 'STRING' && $token !== 'IDENT') {
                return $this->unexpected($token, $value, 'ident or string');
            }
            if ($element >= 0) {
                $attribute = $this->add(self::ATTRIBUTE, $name, $element, $from, $this->at);
                $this->add(self::TEXT, $token === 'STRING' ? substr($value, 1, strlen($value) > 1 && $value[strlen($value) - 1] === $value[0] ? -1 : strlen($value)) : $value, $attribute, $from, $this->at);
            }
            [$token, $text] = $this->token();
        }

        return [$token, $text];
    }

    /**
     * Reads the end of an opening tag from the token after its attributes, or answers the message of its problem.
     *
     * A processing instruction ends with `?>`, an empty element with `/>`, and any other tag with
     * `>`, which opens its element.
     *
     * @param string $mark `?` for a processing instruction, `!` for a declaration, empty for an element
     * @param int $element The element of the tag, -1 when it is none
     * @param list<int> $open The open elements, the root first
     */
    public function end(string $mark, string $token, string $text, int $element, array &$open): ?string
    {
        if ($mark === '?') {
            if ($token !== '?') {
                return $this->unexpected($token, $text, "'?'");
            }
            [$token, $text] = $this->token();

            return $token === '>' ? null : $this->unexpected($token, $text, "'>'");
        }
        if ($token === '/') {
            [$token, $text] = $this->token();
            if ($token !== '>') {
                return $this->unexpected($token, $text, "'>'");
            }
            if ($element >= 0) {
                $this->nodes[$element][4] = $this->at;
            }

            return null;
        }
        if ($token !== '>') {
            return $this->unexpected($token, $text, "'>'");
        }
        if ($element >= 0) {
            $open[] = $element;
        }

        return null;
    }

    /**
     * Reads the next token of a tag: its kind and its text; an unknown character is not consumed.
     *
     * @return array{string, string}
     */
    public function token(): array
    {
        $this->at += strspn($this->text, " \t\r\n", $this->at);
        if ($this->at >= strlen($this->text)) {
            return ['END-OF-INPUT', ''];
        }
        $char = $this->text[$this->at];
        if (str_contains('<>/=?!', $char)) {
            $this->at++;

            return [$char, $char];
        }
        if ($char === '"' || $char === "'") {
            $end = strpos($this->text, $char, $this->at + 1);
            $end = $end === false ? strlen($this->text) : $end + 1;
            $text = substr($this->text, $this->at, $end - $this->at);
            $this->at = $end;

            return ['STRING', $text];
        }
        if (preg_match('/\G[A-Za-z_:\x80-\xFF][A-Za-z0-9_:.\-\x80-\xFF]*/', $this->text, $match, 0, $this->at) === 1) {
            $this->at += strlen($match[0]);

            return ['IDENT', $match[0]];
        }

        return ['unknown token', ''];
    }

    /**
     * Adds a node and answers its number.
     */
    public function add(int $kind, string $text, int $parent, int $start, int $end): int
    {
        $node = count($this->nodes);
        $this->nodes[] = [$kind, $text, $parent, $start, $end];
        $this->children[$parent][] = $node;
        $this->children[$node] = [];

        return $node;
    }

    /**
     * Answers the message of a token the reader did not want.
     */
    public function unexpected(string $token, string $text, string $wanted): string
    {
        $name = match ($token) {
            'IDENT', 'STRING', 'END-OF-INPUT', 'unknown token' => $token,
            default => "'" . $text . "'",
        };

        return $this->problem($name . ' unexpected (' . $wanted . ' wanted)');
    }

    /**
     * Answers a message at the reader: the line and the column of the problem.
     */
    public function problem(string $message): string
    {
        $before = substr($this->text, 0, $this->at);
        $newline = strrpos($before, "\n");

        return 'parse error at line ' . (substr_count($before, "\n") + 1) . ' pos ' . ($this->at - ($newline === false ? 0 : $newline) + 1) . ': ' . $message;
    }

    /**
     * Answers the texts directly in some nodes, in document order.
     *
     * @param list<int> $nodes
     * @return list<string>
     */
    public function texts(array $nodes): array
    {
        $texts = [];
        foreach ($nodes as $node) {
            foreach ($this->children[$node] ?? [] as $child) {
                if ($this->nodes[$child][0] === self::TEXT) {
                    $texts[] = $this->nodes[$child][1];
                }
            }
        }

        return $texts;
    }
}
