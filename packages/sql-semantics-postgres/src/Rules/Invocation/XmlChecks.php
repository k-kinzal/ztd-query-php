<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Invocation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\XmlProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\XmlProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlAttribute;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Reports the SQL/XML constructors the server rejects while analyzing them.
 *
 * Rule: PG-XML-CHECKS-001. An attribute of XMLELEMENT, or a value of
 * XMLFOREST, without AS takes the name of the column it references, so it
 * must be a column reference; the attribute names of one element, written or
 * taken, are distinct (compared exactly); XMLSERIALIZE produces a character
 * string type: `text`, `character varying` or `character`.
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-PRODUCING-XML,
 * `transformXmlExpr` and `transformXmlSerialize` in `src/backend/parser/parse_expr.c` of PostgreSQL 17.
 * Termination: one pass over the values. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class XmlChecks
{
    /**
     * The character string types XMLSERIALIZE accepts.
     */
    private const SERIALIZED = ['text', 'varchar', 'bpchar'];

    /**
     * Reports unnamed attributes that are not column references and attribute names used twice.
     *
     * @param list<XmlAttribute> $attributes
     */
    public function attributes(Derivation $derivation, array $attributes): void
    {
        $names = [];
        foreach ($attributes as $attribute) {
            $name = $attribute->name ?? ($attribute->value instanceof ColumnReference ? $attribute->value->outputName() : null);
            if ($name === null) {
                $derivation->report(new XmlProblem(XmlProblemKind::UnnamedAttribute));
                continue;
            }
            if (in_array($name->value, $names, true)) {
                $derivation->report(new XmlProblem(XmlProblemKind::RepeatedAttribute, $name->value));
            }
            $names[] = $name->value;
        }
    }

    /**
     * Reports unnamed XMLFOREST values that are not column references.
     *
     * @param list<XmlAttribute> $elements
     */
    public function elements(Derivation $derivation, array $elements): void
    {
        foreach ($elements as $element) {
            if ($element->name === null && !$element->value instanceof ColumnReference) {
                $derivation->report(new XmlProblem(XmlProblemKind::UnnamedElement));
            }
        }
    }

    /**
     * Answers the type of an XMLSERIALIZE result, reporting a type that is not a character string type.
     */
    public function serialized(Derivation $derivation, TypeFact $type): TypeFact
    {
        if (!$type instanceof Known) {
            return $type;
        }
        if (in_array((new Coercions())->descriptor($type->descriptor), self::SERIALIZED, true)) {
            return $type;
        }
        $problem = new XmlProblem(XmlProblemKind::SerializeTarget, $type->descriptor->name());
        $derivation->report($problem);

        return new Invalid($problem);
    }
}
