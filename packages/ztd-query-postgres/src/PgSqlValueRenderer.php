<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use RuntimeException;
use Stringable;
use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Platform\ValueRenderer;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;

/**
 * Encodes shadow values using PostgreSQL's typed literal semantics.
 *
 * @phpstan-import-type RenderableValue from ValueRenderer
 */
final class PgSqlValueRenderer implements ValueRenderer
{
    /**
     * Binds the instance to what it will work from.
     *
     * @param CastRenderer $castRenderer
     */
    public function __construct(private readonly CastRenderer $castRenderer = new PgSqlCastRenderer())
    {
    }

    /**
     * @throws RuntimeException
     */
    public function renderValue(mixed $value, ?ColumnDeclaration $type = null): string
    {
        if ($type === null && !$this->isRenderable($value)) {
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
     * Reports whether a value is one an SQL literal could carry on its own.
     *
     * A column that declares its type says how to read whatever is written
     * into it, so anything can be written there; a value with no column
     * behind it has only itself to go on.
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
     * @param mixed $value Value to read
     * @param ColumnDeclaration $type How the column was declared
     * @param bool $typed The typed
     *
     * @return string What it answers
     */
    public function renderExpression(mixed $value, ColumnDeclaration $type, bool $typed): string
    {
        if ($type->family === ColumnTypeFamily::BINARY) {
            $hex = bin2hex($this->stringValue($value));

            return "decode('$hex', 'hex')";
        }

        if (is_bool($value)) {
            return $this->quoteValue($value ? '1' : '0');
        }

        if (is_int($value) && !$typed) {
            return (string) $value;
        }

        if (is_float($value)) {
            return $this->quoteValue(var_export($value, true));
        }

        if (!is_scalar($value) && !$value instanceof Stringable) {
            return $this->quoteValue(serialize($value));
        }

        return $this->quoteValue($this->stringValue($value));
    }

    /**
     * Answers how a value would be read where nothing declared its column.
     *
     * @param mixed $value Value to read
     *
     * @return ColumnDeclaration What it answers
     */
    public function inferType(mixed $value): ColumnDeclaration
    {
        if (is_int($value)) {
            return new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER');
        }

        return new ColumnDeclaration(ColumnTypeFamily::TEXT, 'TEXT');
    }

    /**
     * Answers the bytes a value is, whatever it was handed over as.
     *
     * A driver may hand a large column over as an open stream, and reading it
     * here leaves it where it was so that the caller can read it again.
     *
     * @param mixed $value Value to read
     *
     * @return string What it answers
     *
     * @throws RuntimeException
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
     * @return string What it answers
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
     * @param string $value Value to read
     *
     * @return string What it answers
     */
    public function quoteValue(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
