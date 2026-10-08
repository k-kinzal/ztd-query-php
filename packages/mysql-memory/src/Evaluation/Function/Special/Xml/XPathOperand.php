<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Special\Xml;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Real;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * A value an XPath expression of EXTRACTVALUE computes: a set of nodes, an integer, a double, a string, a truth value or NULL.
 *
 * The server computes XPath with its own SQL operations, so a value converts as SQL converts it:
 * a node set reads as the texts directly in its nodes, joined by spaces; a string reads as a
 * number with the server's warning; a double keeps the decimals of the literals it was computed
 * from and is written with them, or as the server writes a double when they are not fixed
 * (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xml-functions.html.
 *
 * @visibility MySqlMemory
 */
final class XPathOperand
{
    /**
     * A set of nodes.
     */
    public const NODES = 'nodes';

    /**
     * An integer.
     */
    public const INTEGER = 'integer';

    /**
     * A double.
     */
    public const DOUBLE = 'double';

    /**
     * A string.
     */
    public const STRING = 'string';

    /**
     * A truth value, 1 or 0.
     */
    public const BOOLEAN = 'boolean';

    /**
     * NULL.
     */
    public const NULL = 'null';

    /**
     * Decimals of a double that has no fixed decimals.
     */
    public const NOT_FIXED = 31;

    /**
     * @param string $kind What the value is
     * @param int|float|string|null $value The number or string
     * @param list<int> $nodes The nodes of a set, in document order
     * @param int $decimals The decimals a double is written with, or NOT_FIXED
     * @param bool $quiet Whether a string reads as a position without warning, as the value of a user variable does
     */
    public function __construct(public readonly string $kind, public readonly int|float|string|null $value = null, public readonly array $nodes = [], public readonly int $decimals = self::NOT_FIXED, public readonly bool $quiet = false)
    {
    }

    /**
     * Creates a set of nodes, sorted into document order without repeats.
     *
     * @param list<int> $nodes
     */
    public static function nodes(array $nodes): self
    {
        $nodes = array_values(array_unique($nodes));
        sort($nodes);

        return new self(self::NODES, null, $nodes);
    }

    /**
     * Creates a truth value.
     */
    public static function truth(bool $value): self
    {
        return new self(self::BOOLEAN, $value ? 1 : 0);
    }

    /**
     * Tells whether the value is a number: an integer, a double or a truth value.
     */
    public function numeric(): bool
    {
        return $this->kind === self::INTEGER || $this->kind === self::DOUBLE || $this->kind === self::BOOLEAN;
    }

    /**
     * Answers the text of the value, or null for NULL.
     */
    public function text(XmlDocument $document): ?string
    {
        return match ($this->kind) {
            self::NODES => implode(' ', $document->texts($this->nodes)),
            self::INTEGER, self::BOOLEAN => (string) $this->value,
            self::DOUBLE => $this->decimals >= self::NOT_FIXED ? Real::format((float) $this->value) : $this->fixed((float) $this->value),
            self::STRING => (string) $this->value,
            default => null,
        };
    }

    /**
     * Writes a double with its fixed decimals, keeping the sign of a negative zero.
     */
    public function fixed(float $value): string
    {
        $text = sprintf('%.' . $this->decimals . 'F', $value);

        return (ord(pack('E', $value)[0]) & 0x80) !== 0 && !str_starts_with($text, '-') ? '-' . $text : $text;
    }

    /**
     * Answers the value as a double, warning of a string that is not a number, or null for NULL.
     */
    public function real(XmlDocument $document, Frame $frame, Collation $collation): ?float
    {
        return match ($this->kind) {
            self::INTEGER, self::BOOLEAN, self::DOUBLE => (float) $this->value,
            self::NODES, self::STRING => Convert::toDouble($this->text($document), Domain::string(0, $collation), $frame->context),
            default => null,
        };
    }

    /**
     * Answers the truth of the value: a set is true when it holds exactly one node, as the server reads it, anything else when it is a number other than zero.
     */
    public function holds(XmlDocument $document, Frame $frame, Collation $collation): ?bool
    {
        if ($this->kind === self::NODES) {
            return count($this->nodes) === 1;
        }
        $real = $this->real($document, $frame, $collation);

        return $real === null ? null : $real !== 0.0;
    }
}
