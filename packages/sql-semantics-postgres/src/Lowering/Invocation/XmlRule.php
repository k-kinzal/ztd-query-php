<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Invocation;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlConcat;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlElement;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlExists;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlForest;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlIndent;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlParse;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlPassing;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlProcessingInstruction;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlRoot;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlSerialize;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlStandalone;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlWhitespace;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the SQL/XML functions.
 *
 * Rule: PG-XML-LOWERING-001. Scope: the XML productions of
 * `func_expr_common_subexpr`, `xml_root_version`,
 * `opt_xml_root_standalone`, `xml_attributes`, `xml_attribute_list`,
 * `xml_attribute_el`, `xml_indent_option`, `xml_whitespace_option`,
 * `xmlexists_argument`, `xml_passing_mech`. Constructors: `XmlConcat`,
 * `XmlElement`, `XmlAttribute`, `XmlForest`, `XmlParse`,
 * `XmlProcessingInstruction`, `XmlRoot`, `XmlSerialize`, `XmlExists`,
 * `XmlPassing`. BY REF and BY VALUE are accepted and ignored by the server
 * (noise, see `InvocationNoise`). Termination: lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/functions-xml.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class XmlRule
{
    /**
     * The standalone declarations by production signature.
     */
    private const STANDALONE = [
        'opt_xml_root_standalone:' => null,
        'opt_xml_root_standalone: , STANDALONE_P YES_P' => XmlStandalone::Yes,
        'opt_xml_root_standalone: , STANDALONE_P NO' => XmlStandalone::No,
        'opt_xml_root_standalone: , STANDALONE_P NO VALUE_P' => XmlStandalone::NoValue,
    ];

    /**
     * The indentation options by production signature.
     */
    private const INDENT = ['xml_indent_option:' => null, 'xml_indent_option: INDENT' => XmlIndent::Indent, 'xml_indent_option: NO INDENT' => XmlIndent::NoIndent];

    /**
     * The whitespace options by production signature.
     */
    private const WHITESPACE = ['xml_whitespace_option:' => null, 'xml_whitespace_option: PRESERVE WHITESPACE_P' => XmlWhitespace::Preserve, 'xml_whitespace_option: STRIP_P WHITESPACE_P' => XmlWhitespace::Strip];

    /**
     * The PASSING forms by production signature: the position of the document.
     */
    private const PASSING = [
        'xmlexists_argument: PASSING c_expr' => 1,
        'xmlexists_argument: PASSING c_expr xml_passing_mech' => 1,
        'xmlexists_argument: PASSING xml_passing_mech c_expr' => 2,
        'xmlexists_argument: PASSING xml_passing_mech c_expr xml_passing_mech' => 2,
    ];

    /**
     * The passing mechanisms, which the server ignores.
     */
    private const MECHANISMS = ['xml_passing_mech: BY REF_P', 'xml_passing_mech: BY VALUE_P'];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an XML production of `func_expr_common_subexpr`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function expression(Form $form): Scalar
    {
        $expressions = $this->lowering->expressions;
        $names = $this->lowering->names;

        return match ($form->signature) {
            'func_expr_common_subexpr: XMLCONCAT ( expr_list )' => new XmlConcat($expressions->expressions($form->node(2))),
            'func_expr_common_subexpr: XMLELEMENT ( NAME_P ColLabel )' => new XmlElement($names->name($form->node(3))),
            'func_expr_common_subexpr: XMLELEMENT ( NAME_P ColLabel , xml_attributes )' => new XmlElement($names->name($form->node(3)), $this->wrapped($form->node(5))),
            'func_expr_common_subexpr: XMLELEMENT ( NAME_P ColLabel , expr_list )' => new XmlElement($names->name($form->node(3)), [], $expressions->expressions($form->node(5))),
            'func_expr_common_subexpr: XMLELEMENT ( NAME_P ColLabel , xml_attributes , expr_list )' => new XmlElement($names->name($form->node(3)), $this->wrapped($form->node(5)), $expressions->expressions($form->node(7))),
            'func_expr_common_subexpr: XMLEXISTS ( c_expr xmlexists_argument )' => new XmlExists($expressions->expression($form->node(2)), $this->passing($form->node(3))),
            'func_expr_common_subexpr: XMLFOREST ( xml_attribute_list )' => new XmlForest($this->attributes($form->node(2))),
            'func_expr_common_subexpr: XMLPARSE ( document_or_content a_expr xml_whitespace_option )' => new XmlParse(
                $this->lowering->flags->xmlOption($form->node(2)),
                $expressions->expression($form->node(3)),
                $this->option(self::WHITESPACE, $form->node(4)),
            ),
            'func_expr_common_subexpr: XMLPI ( NAME_P ColLabel )' => new XmlProcessingInstruction($names->name($form->node(3))),
            'func_expr_common_subexpr: XMLPI ( NAME_P ColLabel , a_expr )' => new XmlProcessingInstruction($names->name($form->node(3)), $expressions->expression($form->node(5))),
            'func_expr_common_subexpr: XMLROOT ( a_expr , xml_root_version opt_xml_root_standalone )' => $this->root($form),
            'func_expr_common_subexpr: XMLSERIALIZE ( document_or_content a_expr AS SimpleTypename xml_indent_option )' => new XmlSerialize(
                $this->lowering->flags->xmlOption($form->node(2)),
                $expressions->expression($form->node(3)),
                new TypeName($this->lowering->types->simple($form->node(5))),
                $this->option(self::INDENT, $form->node(6)),
            ),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the XMLROOT production.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function root(Form $form): XmlRoot
    {
        $value = $this->lowering->expressions->expression($form->node(2));
        $version = $this->lowering->productions->form($form->node(4));
        $expression = match ($version->signature) {
            'xml_root_version: VERSION_P a_expr' => $this->lowering->expressions->expression($version->node(1)),
            'xml_root_version: VERSION_P NO VALUE_P' => null,
            default => throw ImplementationGap::production($version),
        };

        return new XmlRoot($value, $expression, $this->option(self::STANDALONE, $form->node(5)));
    }

    /**
     * Answers the option a production of a closed table selects.
     *
     * @template T
     *
     * @param array<string, T> $table
     *
     * @return T
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function option(array $table, Node $option): mixed
    {
        $form = $this->lowering->productions->form($option);
        if (!array_key_exists($form->signature, $table)) {
            throw ImplementationGap::production($form);
        }

        return $table[$form->signature];
    }

    /**
     * Lowers `xml_attributes`.
     *
     * @return list<XmlAttribute>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function wrapped(Node $attributes): array
    {
        $form = $this->lowering->productions->form($attributes);
        if ($form->signature !== 'xml_attributes: XMLATTRIBUTES ( xml_attribute_list )') {
            throw ImplementationGap::production($form);
        }

        return $this->attributes($form->node(2));
    }

    /**
     * Lowers `xml_attribute_list`.
     *
     * @return list<XmlAttribute>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function attributes(Node $list): array
    {
        $attributes = [];
        foreach ($this->lowering->items($list, 'xml_attribute_list: xml_attribute_el', 'xml_attribute_list: xml_attribute_list , xml_attribute_el') as $element) {
            $form = $this->lowering->productions->form($element);
            $attributes[] = match ($form->signature) {
                'xml_attribute_el: a_expr AS ColLabel' => new XmlAttribute($this->lowering->expressions->expression($form->node(0)), $this->lowering->names->name($form->node(2))),
                'xml_attribute_el: a_expr' => new XmlAttribute($this->lowering->expressions->expression($form->node(0))),
                default => throw ImplementationGap::production($form),
            };
        }

        return $attributes;
    }

    /**
     * Lowers `xmlexists_argument`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function passing(Node $argument): XmlPassing
    {
        $form = $this->lowering->productions->form($argument);
        $position = self::PASSING[$form->signature] ?? throw ImplementationGap::production($form);
        foreach ($form->node->children as $child) {
            if ($child instanceof Node && $child->name === 'xml_passing_mech') {
                $this->option(array_fill_keys(self::MECHANISMS, true), $child);
            }
        }

        return new XmlPassing($this->lowering->expressions->expression($form->node($position)));
    }
}
