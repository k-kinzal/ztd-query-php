<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Literal;

use SqlSemantics\Platform\MySql\Ir\Binding\ProjectionMode;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A decoded utf8mb4 literal under the owning statement's native connection profile.
 *
 * @visibility public
 * @example Retaining the literal value
 *     (new \SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral('value'))->value // => 'value'
 */
final class TextLiteral
{
    use Snapshot;

    /**
     * Accepts decoded UTF-8, including Unicode supplementary code points and NUL.
     */
    public function __construct(public readonly string $value)
    {
        Check::input(preg_match('//u', $value) === 1, 'A MySQL text literal requires valid UTF-8.');
    }

    /**
     * Emits the decoded value under its owner's escape rule and utf8mb4 connection.
     */
    public function toString(ProjectionMode $mode = ProjectionMode::Empty): string
    {
        if (!$mode->escapesBackslash()) {
            return "'" . str_replace("'", "''", $this->value) . "'";
        }

        return "'" . strtr($this->value, [
            '\\' => '\\\\', "'" => "''", "\0" => '\\0', "\x08" => '\\b', "\t" => '\\t',
            "\n" => '\\n', "\r" => '\\r', "\x1a" => '\\Z',
        ]) . "'";
    }
}
