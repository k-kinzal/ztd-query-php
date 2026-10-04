<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One column of XMLTABLE: a typed column with its options, or the FOR ORDINALITY column.
 *
 * Mirrors PostgreSQL's `RangeTableFuncCol`.
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-XML-PROCESSING-XMLTABLE.
 *
 * @visibility public
 * @example Reading an ordinality column
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlTableColumn(new \SqlSemantics\Statement\Identifier\Name('n')))->ordinality() // => true
 */
final class XmlTableColumn implements Node
{
    use Snapshot;

    /**
     * @var list<XmlColumnOption> The options in written order
     */
    public readonly array $options;

    /**
     * @param Name $name The column name
     * @param TypeName|null $type The column type; null for FOR ORDINALITY
     * @param list<XmlColumnOption> $options The options in written order
     */
    public function __construct(public readonly Name $name, public readonly ?TypeName $type = null, array $options = [])
    {
        $this->options = Check::listOf($options, XmlColumnOption::class, 'Column options are XMLTABLE column options.');
        Check::input($type !== null || $this->options === [], 'The FOR ORDINALITY column has no options.');
    }

    /**
     * Tells whether the column numbers the rows.
     */
    public function ordinality(): bool
    {
        return $this->type === null;
    }

    /**
     * Writes the name and FOR ORDINALITY, or the type and the options.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Column);
        if ($this->type === null) {
            $out->keyword('FOR', 'ORDINALITY');

            return;
        }
        $out->node($this->type);
        foreach ($this->options as $option) {
            $out->node($option);
        }
    }
}
