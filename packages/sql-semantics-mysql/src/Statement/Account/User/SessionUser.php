<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\User;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Call\Keyword\KeywordAlias;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * `USER()` at the account position of ALTER USER: the account of the session.
 *
 * ALTER USER USER() changes the password or the registration of the
 * session's own account; it is written only in the forms that a user may
 * apply to itself.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-user.html.
 *
 * @visibility public
 * @example Rendering the session account
 *     $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\MySql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::MySql847));
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\User\SessionUser())->render($out);
 *     $out->pieces()[0]->text // => 'USER'
 */
final class SessionUser implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * Writes `USER()`.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->alias === null ? 'USER' : $this->alias->value)->glue()->symbol('(')->glue()->symbol(')');
    }
    /**
     * Constructs this SQL form with optional completed semantics and an input range.
     */
    public function __construct(public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly ?KeywordAlias $alias = null)
    {
        Check::input($alias === null || $alias->function() === KeywordFunction::User, 'The session account is named by USER or one of its synonyms.');
    }
}
