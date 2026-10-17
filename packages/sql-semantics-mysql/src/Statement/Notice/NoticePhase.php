<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Notice;

/**
 * The stage of interpreting an input at which an ordered condition is raised.
 *
 * A debug parse-tree inspection reads grammar operands but does not contextualize
 * the inspected statement. Ordinary statements reach both stages before execution.
 * This records the origin of a diagnostic, not whether a statement can execute.
 * Observed with SHOW PARSE_TREE on MySQL 8.4.7 and 9.1.0.
 *
 * @visibility public
 * @example The phase of an ordinary syntax condition
 *     $notice = new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation('missing'));
 *     $notice->phase // => \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Syntax
 */
enum NoticePhase: string
{
    case Syntax = 'syntax';
    case Contextualization = 'contextualization';
}
