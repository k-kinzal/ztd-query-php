<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\View;

use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;

/**
 * MySQL view declaration properties: processing algorithm, definer, and privilege context.
 * @visibility public
 * @example Inspecting a declared definer
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE DEFINER='app'@'localhost' VIEW v AS SELECT 1");
 *     $statement->properties->definer->username // => 'app'
 */
final class MySqlViewProperties
{
    /**
     * An omitted definer denotes the account executing the declaration. A null security is a clause the statement
     * does not write: ALTER VIEW then keeps the view's current SQL SECURITY, which an explicit DEFINER would replace,
     * while CREATE VIEW binds an omitted clause as DEFINER.
     */
    public function __construct(
        public readonly ViewAlgorithm $algorithm = ViewAlgorithm::Undefined,
        public readonly AccountName|CurrentAccount|null $definer = null,
        public readonly ?ViewSecurity $security = ViewSecurity::Definer,
    ) {
    }
}
