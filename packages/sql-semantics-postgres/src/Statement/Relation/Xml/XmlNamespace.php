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
 * One namespace of XMLNAMESPACES: a URI with its prefix, or the DEFAULT namespace.
 *
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-XML-PROCESSING-XMLTABLE.
 *
 * @visibility public
 * @example Reading a namespace
 *     $namespace = new \SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlNamespace(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('http://example.com')), new \SqlSemantics\Statement\Identifier\Name('x'));
 *     $namespace->prefix->value // => 'x'
 */
final class XmlNamespace implements Node
{
    use Snapshot;

    /**
     * @param Scalar $uri The namespace URI, an expression of the restricted form `b_expr`
     * @param Name|null $prefix The prefix; null for the DEFAULT namespace
     */
    public function __construct(public readonly Scalar $uri, public readonly ?Name $prefix = null)
    {
        Check::input((new Precedence())->restricted($uri), 'A namespace URI is an expression without boolean, IS or pattern operators.');
    }

    /**
     * Writes the URI AS the prefix, or DEFAULT and the URI.
     */
    public function render(Output $out): void
    {
        if ($this->prefix === null) {
            $out->keyword('DEFAULT')->node($this->uri);

            return;
        }
        $out->node($this->uri)->keyword('AS')->name($this->prefix, NameUse::Label);
    }
}
