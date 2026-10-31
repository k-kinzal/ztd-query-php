<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Keyword;

use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;

/**
 * A keyword synonym that retains the same built-in function identity.
 *
 * @visibility public
 * @example Resolving a synonym to its function identity
 *     \SqlSemantics\Platform\MySql\Statement\Call\Keyword\KeywordAlias::Mid->function()->value // => 'SUBSTRING'
 */
enum KeywordAlias: string
{
    case Schema = 'SCHEMA';
    case SessionUser = 'SESSION_USER';
    case SystemUser = 'SYSTEM_USER';
    case Substr = 'SUBSTR';
    case Mid = 'MID';
    case GeomCollection = 'GEOMCOLLECTION';
    case SqlTsiYear = 'SQL_TSI_YEAR';
    case SqlTsiWeek = 'SQL_TSI_WEEK';

    /**
     * Answers the built-in function named by this synonym.
     */
    public function function(): KeywordFunction
    {
        return match ($this) {
            self::Schema => KeywordFunction::Database,
            self::SessionUser, self::SystemUser => KeywordFunction::User,
            self::Substr, self::Mid => KeywordFunction::Substring,
            self::GeomCollection => KeywordFunction::GeometryCollection,
            self::SqlTsiYear => KeywordFunction::Year,
            self::SqlTsiWeek => KeywordFunction::Week,
        };
    }
}
