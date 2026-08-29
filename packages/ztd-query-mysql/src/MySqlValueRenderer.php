<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use RuntimeException;
use Stringable;
use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Platform\ValueRenderer;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;

/**
 * Writes a shadow value as MySQL would read it back, without relying on how the server escapes strings.
 *
 * What a backslash means inside a string literal is a server setting, so a
 * value carrying one is written as bytes and converted back instead of being
 * quoted -- which reads the same whichever way the server is set.
 *
 * @phpstan-import-type RenderableValue from ValueRenderer
 */
final class MySqlValueRenderer implements ValueRenderer
{
    /**
     * @param CastRenderer $castRenderer Writes the cast that says how a literal is to be read
     */
    public function __construct(private readonly CastRenderer $castRenderer = new MySqlCastRenderer())
    {
    }

    /**
     * {@inheritDoc}
     *
     * @throws RuntimeException When the value is of a kind no SQL literal can carry
     */
    public function renderValue(mixed $value, ?ColumnDeclaration $type = null): string
    {
        if (!$this->isRenderable($value)) {
            throw new RuntimeException('Unsupported value type for CTE shadowing.');
        }
        if ($value === null) {
            return 'NULL';
        }

        if ($type === null && is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if ($type === null && is_float($value)) {
            return var_export($value, true);
        }

        if ($type === null && $value instanceof Stringable) {
            return (string) $value;
        }

        $resolvedType = $type ?? $this->inferType($value);
        $expression = $this->renderExpression($value, $resolvedType, $type !== null);

        return $this->castRenderer->renderCast($expression, $resolvedType);
    }

    /**
     * Reports whether a value is one an SQL literal could carry at all.
     *
     * @param mixed $value Value as it was handed over
     *
     * @return bool True when it can be written
     *
     * @phpstan-assert-if-true RenderableValue $value
     */
    public function isRenderable(mixed $value): bool
    {
        return $value === null
            || is_scalar($value)
            || $value instanceof Stringable
            || (is_resource($value) && get_resource_type($value) === 'stream');
    }

    /**
     * Writes the literal a value becomes, before anything says how to read it.
     *
     * A string carrying a backslash is written as bytes, because whether a
     * backslash escapes the byte after it inside a literal is a server
     * setting, and the same statement would otherwise mean two things.
     *
     * @param RenderableValue $value Value to write
     * @param ColumnDeclaration $type How the value will be read
     * @param bool $typed Whether the column itself declared that type
     *
     * @return string The literal
     *
     * @throws RuntimeException When the value is of a kind no SQL literal can carry
     */
    public function renderExpression(mixed $value, ColumnDeclaration $type, bool $typed): string
    {
        if ($type->family === ColumnTypeFamily::BINARY) {
            return "X'" . bin2hex($this->stringValue($value)) . "'";
        }

        if (is_int($value) && !$typed) {
            return (string) $value;
        }

        $string = is_float($value) ? var_export($value, true) : $this->stringValue($value);
        if (!str_contains($string, '\\')) {
            return $this->quoteValue($string);
        }
        $hex = bin2hex($string);

        return "CONVERT(X'$hex' USING utf8mb4)";
    }

    /**
     * Answers how a value would be read where nothing declared its column.
     *
     * @param RenderableValue $value Value to read
     *
     * @return ColumnDeclaration The declaration the value itself suggests
     */
    public function inferType(mixed $value): ColumnDeclaration
    {
        if (is_int($value)) {
            return new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INT');
        }

        return new ColumnDeclaration(ColumnTypeFamily::STRING, 'VARCHAR');
    }

    /**
     * Answers the bytes a value is, whatever it was handed over as.
     *
     * A driver may hand a large column over as an open stream rather than as
     * its contents, and reading it here leaves it where it was so that the
     * caller can read it again.
     *
     * @param RenderableValue $value Value to read
     *
     * @return string The value's bytes
     *
     * @throws RuntimeException When the value is of a kind no SQL literal can carry
     */
    public function stringValue(mixed $value): string
    {
        if ($value instanceof Stringable) {
            return (string) $value;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_resource($value) && get_resource_type($value) === 'stream') {
            return $this->readStream($value);
        }

        throw new RuntimeException('Unsupported value type for CTE shadowing.');
    }

    /**
     * Reads an open stream without moving where the caller had it.
     *
     * @param resource $stream Stream to read
     *
     * @return string Everything it holds
     */
    public function readStream($stream): string
    {
        $position = ftell($stream);
        rewind($stream);
        $contents = stream_get_contents($stream);
        if ($position !== false) {
            fseek($stream, $position);
        }

        return $contents === false ? '' : $contents;
    }

    /**
     * Writes bytes as a quoted literal.
     *
     * @param string $value Bytes to quote
     *
     * @return string The literal, with each quote doubled
     */
    public function quoteValue(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
