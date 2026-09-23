<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Configuration;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\Transaction as Statement;
use SqlSemantics\Model\Statement\ConfigurationStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Transaction\Access;
use SqlSemantics\Model\Transaction\Configuration\Deferrability;
use SqlSemantics\Model\Transaction\Configuration\Locality;
use SqlSemantics\Model\Transaction\Isolation;
use SqlSemantics\Model\Validation\Collections;

/**
 * Binds transaction policy requests at their grammar boundaries, separate from variable assignments.
 * @visibility SqlSemantics
 */
final class TransactionSettings
{
    /**
     * Separates current, next, default, and imported-snapshot operations without applying them.
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source): ?ConfigurationStatement
    {
        if ($origin->dialect === Dialect::MySql && $source->name === 'set') {
            return Transaction\MySqlSettings::bind($origin, $source);
        }
        if ($origin->dialect !== Dialect::PostgreSql || $source->name !== 'VariableSetStmt') {
            return null;
        }
        $rest = Tree::child($source, ['set_rest']);
        if ($rest === null) {
            return null;
        }
        $locality = ($source->tokens()[1]->name ?? '') === 'LOCAL' ? Locality::Local : Locality::Session;
        $list = Tree::child($rest, ['transaction_mode_list']);
        if ($list === null) {
            return Transaction\SnapshotBinder::bind($origin, $rest, $locality);
        }
        $modes = [];
        foreach (Tree::outer($list, ['transaction_mode_item']) as $item) {
            $isolation = Tree::child($item, ['iso_level']);
            $text = strtoupper(Tree::text($item));
            $modes[] = $isolation !== null ? Isolation::from(strtoupper(Tree::text($isolation))) : (Access::tryFrom($text) ?? Deferrability::from($text));
        }
        return ($rest->tokens()[0]->name ?? '') === 'SESSION'
            ? new Statement\SetSessionTransactionStatement($origin, Collections::nonEmpty($modes), $locality)
            : new Statement\SetCurrentTransactionStatement($origin, Collections::nonEmpty($modes), $locality);
    }




}
