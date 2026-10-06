<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Problem;

/**
 * The rules a command breaks when it names a relation of a kind it does not accept, in the words of the server.
 *
 * `%1$s` stands for the relation name, `%2$s` for the ALTER action.
 * Source: `tablecmds.c` (DropErrorMsgWrongType, RangeVarCallbackForAlterRelation, ATSimplePermissions,
 * truncate_check_rel, MergeAttributes, renameatt_check), `objectaddress.c`, `matview.c`, `indexcmds.c`,
 * `execMain.c` (CheckValidResultRel, CheckValidRowMarkRel), `parse_merge.c`, `copyto.c`, `copyfrom.c`,
 * `lockcmds.c`, `parse_utilcmd.c`, `trigger.c`, `rewriteDefine.c`, `policy.c`, `statscmds.c`, `sequence.c`.
 *
 * @visibility public
 * @example Reading the rule a DROP VIEW of a table breaks
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
 *     $semantics->analyze('DROP VIEW t', [$semantics->analyze('CREATE TABLE t (a int)')])->facts->diagnostics[0]->rule // => \SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindRule::NotView
 */
enum KindRule: string
{
    case NotTable = '"%1$s" is not a table';
    case NotView = '"%1$s" is not a view';
    case NotMaterializedView = '"%1$s" is not a materialized view';
    case NotForeignTable = '"%1$s" is not a foreign table';
    case NotSequence = '"%1$s" is not a sequence';
    case NotIndex = '"%1$s" is not an index';
    case NotTableOrMaterializedView = '"%1$s" is not a table or materialized view';
    case AlterAction = 'ALTER action %2$s cannot be performed on relation "%1$s"';
    case RenameColumns = 'cannot rename columns of relation "%1$s"';
    case OpenSequence = 'cannot open relation "%1$s"';
    case ChangeSequence = 'cannot change sequence "%1$s"';
    case ChangeMaterializedView = 'cannot change materialized view "%1$s"';
    case Merge = 'cannot execute MERGE on relation "%1$s"';
    case LockSequenceRows = 'cannot lock rows in sequence "%1$s"';
    case LockMaterializedViewRows = 'cannot lock rows in materialized view "%1$s"';
    case LockRelation = 'cannot lock relation "%1$s"';
    case IndexRelation = 'cannot create index on relation "%1$s"';
    case CopyFromView = 'cannot copy from view "%1$s"';
    case CopyFromMaterializedView = 'cannot copy from materialized view "%1$s"';
    case CopyFromForeignTable = 'cannot copy from foreign table "%1$s"';
    case CopyFromSequence = 'cannot copy from sequence "%1$s"';
    case CopyToMaterializedView = 'cannot copy to materialized view "%1$s"';
    case CopyToSequence = 'cannot copy to sequence "%1$s"';
    case LikeSource = 'relation "%1$s" is invalid in LIKE clause';
    case InheritedRelation = 'inherited relation "%1$s" is not a table or foreign table';
    case ReferencedRelation = 'referenced relation "%1$s" is not a table';
    case TriggersOnRelation = 'relation "%1$s" cannot have triggers';
    case TriggerOnTable = '"%1$s" is a table';
    case TriggerOnView = '"%1$s" is a view';
    case TriggerOnForeignTable = '"%1$s" is a foreign table';
    case RulesOnRelation = 'relation "%1$s" cannot have rules';
    case RulesOnMaterializedView = 'rules on materialized views are not supported';
    case SequenceOwner = 'sequence cannot be owned by relation "%1$s"';
    case StatisticsRelation = 'cannot define statistics for relation "%1$s"';
    case ForeignPrimaryKey = 'primary key constraints are not supported on foreign tables';
    case ForeignUnique = 'unique constraints are not supported on foreign tables';
    case ForeignExclusion = 'exclusion constraints are not supported on foreign tables';
    case ForeignForeignKey = 'foreign key constraints are not supported on foreign tables';

    /**
     * Tells whether the message names the relation.
     */
    public function namesRelation(): bool
    {
        return str_contains($this->value, '%1$s');
    }
}
