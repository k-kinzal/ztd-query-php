<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One option of an XMLTABLE column: its path, its default, or whether it can be NULL.
 *
 * Mirrors the `DefElem` options PostgreSQL's grammar builds for
 * `xmltable_column_option_el`.
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-XML-PROCESSING-XMLTABLE.
 *
 * @visibility public
 * @example Reading an option written as an identifier
 *     $option = new \SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlColumnOption(\SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlColumnOptionKind::Named, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('a')), new \SqlSemantics\Statement\Identifier\Name('path'));
 *     $option->word->value // => 'path'
 * @example Refusing a value for NOT NULL
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlColumnOption(\SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlColumnOptionKind::NotNull, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class XmlColumnOption implements Node
{
    use Snapshot;

    /**
     * @param XmlColumnOptionKind $kind The option
     * @param Scalar|null $value The value of PATH, DEFAULT or a named option, an expression of the restricted form `b_expr`
     * @param Name|null $word The identifier a named option is written with
     */
    public function __construct(public readonly XmlColumnOptionKind $kind, public readonly ?Scalar $value = null, public readonly ?Name $word = null)
    {
        Check::input($kind->valued() === ($value !== null), 'PATH, DEFAULT and named options have a value; NULL and NOT NULL have none.');
        Check::input(($kind === XmlColumnOptionKind::Named) === ($word !== null), 'Only a named option has a word.');
        Check::input($value === null || (new Precedence())->restricted($value), 'An option value is an expression without boolean, IS or pattern operators.');
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        if ($this->word !== null) {
            $out->name($this->word, NameUse::Identifier);
        } else {
            $out->keyword(...explode(' ', $this->kind->value));
        }
        $out->node($this->value);
    }
}
