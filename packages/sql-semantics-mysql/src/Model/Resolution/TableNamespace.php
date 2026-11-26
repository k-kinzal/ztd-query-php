<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Resolution;

/**
 * Identifies the namespaces consulted by a native relation operation.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
enum TableNamespace
{
    case Visible;
    case Persistent;
    case Temporary;
    case PersistentPreferred;
}
