<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Storage\Option;

use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * `ENGINE_ATTRIBUTE [=] 'json'`: attributes for a secondary engine (MySQL 8.0.21 and later).
 *
 * The equals sign is optional and not written. The text is a JSON document the engine reads.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html.
 *
 * @visibility public
 * @example Holding the option
 *     (new \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EngineAttributeOption(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('{}')))->attribute->value // => '{}'
 */
final class EngineAttributeOption implements StorageOption
{
    use Snapshot;

    /**
     * @param Text $attribute The JSON text
     */
    public function __construct(public readonly Text $attribute)
    {
    }

    /**
     * Answers the keyword of the option.
     */
    public function keyword(): string
    {
        return 'ENGINE_ATTRIBUTE';
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('ENGINE_ATTRIBUTE')->node($this->attribute);
    }
}
