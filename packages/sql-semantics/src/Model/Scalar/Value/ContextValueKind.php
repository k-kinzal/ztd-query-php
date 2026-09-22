<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Value;

/**

 * Named requests for runtime session and clock information; no value is evaluated. @visibility public

 */
enum ContextValueKind: string
{
    case CurrentDate = 'CURRENT_DATE';
    case CurrentTime = 'CURRENT_TIME';
    case CurrentTimestamp = 'CURRENT_TIMESTAMP';
    case LocalTime = 'LOCALTIME';
    case LocalTimestamp = 'LOCALTIMESTAMP';
    case CurrentUser = 'CURRENT_USER';
    case SessionUser = 'SESSION_USER';
    case SystemUser = 'SYSTEM_USER';
    case User = 'USER';
    case CurrentRole = 'CURRENT_ROLE';
    case CurrentSchema = 'CURRENT_SCHEMA';
    case CurrentCatalog = 'CURRENT_CATALOG';
}
