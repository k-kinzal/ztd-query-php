<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dispatch;

/**
 * The statement families a statement root production is lowered by.
 *
 * `Definition` stands for the rules `create`, `alter` and `drop` of MySQL
 * 5.6 and 5.7 and `create` of later releases, whose productions belong to
 * several families and are routed by DefinitionRoutes.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
enum Family
{
    case Account;
    case Definition;
    case Dml;
    case Query;
    case Replication;
    case Routine;
    case Server;
    case TableChange;
    case TableDefinition;
    case Utility;
}
