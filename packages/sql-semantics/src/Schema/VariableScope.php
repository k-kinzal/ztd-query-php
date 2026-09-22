<?php

declare(strict_types=1);

namespace SqlSemantics\Schema;

/**

 * Declared variable storage namespaces; these do not contain runtime values. @visibility public

 */
enum VariableScope: string
{
    case User = 'user';
    case Session = 'session';
    case Global = 'global';
    case Local = 'local';
}
