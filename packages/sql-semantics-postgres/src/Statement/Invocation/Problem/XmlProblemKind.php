<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem;

/**
 * A mistake in an SQL/XML constructor that the server reports while analyzing the statement.
 *
 * Each case carries the server's message, with `%s` for the subject.
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-PRODUCING-XML,
 * `transformXmlExpr` and `transformXmlSerialize` in `src/backend/parser/parse_expr.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading the message of an unnamed attribute
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\XmlProblemKind::UnnamedAttribute->value // => 'unnamed XML attribute value must be a column reference'
 */
enum XmlProblemKind: string
{
    case UnnamedAttribute = 'unnamed XML attribute value must be a column reference';
    case UnnamedElement = 'unnamed XML element value must be a column reference';
    case RepeatedAttribute = 'XML attribute name "%s" appears more than once';
    case SerializeTarget = 'cannot cast XMLSERIALIZE result to %s';
}
