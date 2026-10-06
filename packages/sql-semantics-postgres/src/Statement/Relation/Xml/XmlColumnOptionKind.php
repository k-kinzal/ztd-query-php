<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml;

/**
 * The kinds of column options of XMLTABLE.
 *
 * An option written as an identifier followed by a value is kept as Named
 * with its word: PostgreSQL reads `path` (before release 17 PATH is no
 * keyword) and `default` written so as those options and rejects any other
 * word. Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-XML-PROCESSING-XMLTABLE.
 *
 * @visibility public
 * @example Telling which kinds carry a value
 *     [\SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlColumnOptionKind::Path->valued(), \SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlColumnOptionKind::NotNull->valued()] // => [true, false]
 */
enum XmlColumnOptionKind: string
{
    case Default = 'DEFAULT';
    case Path = 'PATH';
    case NotNull = 'NOT NULL';
    case Null = 'NULL';
    case Named = '';

    /**
     * Tells whether the option is written with a value.
     */
    public function valued(): bool
    {
        return match ($this) {
            self::Default, self::Path, self::Named => true,
            self::NotNull, self::Null => false,
        };
    }
}
