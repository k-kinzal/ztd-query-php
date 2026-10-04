<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Option;

/**
 * Whether a CREATE USER or ALTER USER sets the user attribute JSON object or its comment.
 *
 * `ATTRIBUTE '{…}'` merges a JSON object into the account's attributes;
 * `COMMENT '…'` sets the `comment` member.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-comments-attributes.
 *
 * @visibility public
 * @example Reading the keyword
 *     \SqlSemantics\Platform\MySql\Statement\Account\Option\CommentKind::Attribute->value // => 'ATTRIBUTE'
 */
enum CommentKind: string
{
    case Attribute = 'ATTRIBUTE';
    case Comment = 'COMMENT';
}
