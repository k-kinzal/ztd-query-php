<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Instance;

use SqlSemantics\Rendering\Output;

/**
 * `RELOAD KEYRING`: reinitialize the keyring component from its configuration (MySQL 8.0.24 and later).
 *
 * Mirrors RELOAD_KEYRING.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-instance.html.
 *
 * @visibility public
 * @example Reloading the keyring
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('alter instance reload keyring')->toString() // => 'ALTER INSTANCE RELOAD KEYRING'
 */
final class ReloadKeyring implements InstanceAction
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('RELOAD', 'KEYRING');
    }
    /**
     * Constructs this SQL form with optional completed semantics and an input range.
     */
    public function __construct(public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }
}
