<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key\Option;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownIndexParser;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

/**
 * The WITH PARSER option of a full-text index: the full-text parser plugin.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html.
 *
 * @visibility public
 * @example Reading an index option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a TEXT, FULLTEXT (a) WITH PARSER ngram)');
 *     $create->elements[1]->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexParser // => true
 */
final class IndexParser implements IndexOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Name $parser The parser plugin
     */
    public function __construct(public readonly Name $parser, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }

    /**
     * Resolves the plugin name when the context supplies a complete parser catalog.
     */
    public function problem(Settings $settings): ?UnknownIndexParser
    {
        if ($settings->fullTextParsers === null) {
            return null;
        }
        foreach ($settings->fullTextParsers as $available) {
            if (strcasecmp($available->value, $this->parser->value) === 0) {
                return null;
            }
        }
        return new UnknownIndexParser($this->parser);
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('WITH', 'PARSER')->name($this->parser, NameUse::Label);
    }
}
