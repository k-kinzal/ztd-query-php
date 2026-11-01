<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Json;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Text\Json\Failure;
use SqlSemantics\Platform\MySql\Rules\Text\Json\PathSyntax;
use SqlSemantics\Statement\Snapshot;

/**
 * The selection operations denoted by a JSON path operand.
 *
 * This structure contains member names and array indices, independently of SQL
 * syntax and execution. A document consumer applies the steps to its values.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json.html.
 *
 * @visibility public
 * @example Reading a member after an array cell
 *     count(\SqlSemantics\Platform\MySql\Statement\Call\Json\Path::read('$[0].a')->legs) // => 2
 * @example Refusing an incomplete recursive descent
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Json\Path([new \SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Leg(\SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Kind::Descendants)]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Path
{
    use Snapshot;

    /**
     * @var list<Path\Leg> The operations in selection order
     */
    public readonly array $legs;

    /**
     * @param list<Path\Leg> $legs The steps after the root, empty for the root itself
     */
    public function __construct(array $legs)
    {
        $this->legs = Check::listOf($legs, Path\Leg::class, 'A JSON path holds selection steps.');
        foreach ($this->legs as $index => $leg) {
            Check::input($leg->kind !== Path\Kind::Descendants || (isset($this->legs[$index + 1]) && $this->legs[$index + 1]->kind !== Path\Kind::Descendants), 'Recursive descent must precede a member or array step.');
        }
    }

    /**
     * Interprets a decoded constant operand into a path or its typed condition.
     */
    public static function read(string $text): self|Path\Invalid
    {
        try {
            return PathSyntax::parse($text);
        } catch (Failure $failure) {
            return new Path\Invalid($failure->position);
        }
    }
}
