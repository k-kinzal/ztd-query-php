<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * `FORMAT JSON [ENCODING name]`: the value is JSON text in an encoding.
 *
 * Mirrors PostgreSQL's `JsonFormat` node with format JSON. The encoding name
 * is kept as written; the server compares it without case.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-JSON-CREATION-TABLE.
 *
 * @visibility public
 * @example Reading the encoding of a format
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonFormat(new \SqlSemantics\Statement\Identifier\Name('utf16')))->encoding() // => \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonEncoding::Utf16
 * @example Rejecting an unknown encoding
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonFormat(new \SqlSemantics\Statement\Identifier\Name('latin1')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class JsonFormat implements Clause
{
    use Snapshot;

    /**
     * @param Name|null $encodingName The encoding name written after ENCODING
     */
    public function __construct(public readonly ?Name $encodingName = null)
    {
        Check::input($encodingName === null || JsonEncoding::named($encodingName->value) !== null, 'A JSON encoding is UTF8, UTF16 or UTF32.');
    }

    /**
     * Answers the encoding written, or null when none is.
     */
    public function encoding(): ?JsonEncoding
    {
        return $this->encodingName === null ? null : JsonEncoding::named($this->encodingName->value);
    }

    /**
     * Derives nothing: the clause holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes FORMAT JSON and the encoding.
     */
    public function render(Output $out): void
    {
        $out->keyword('FORMAT', 'JSON');
        if ($this->encodingName !== null) {
            $out->keyword('ENCODING')->name($this->encodingName, NameUse::Column);
        }
    }
}
