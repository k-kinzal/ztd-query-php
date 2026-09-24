<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Trigger;

use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The trigger function a trigger executes and the constant text arguments passed to it.
 * @visibility public
 * @example Reading the executed function and its arguments
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind("CREATE TRIGGER audit AFTER INSERT ON t EXECUTE PROCEDURE audit.log_change(1, 'x', label)");
 *     $statement->invocation->function->parts // => ['audit', 'log_change']
 *     $statement->invocation->arguments // => ['1', 'x', 'label']
 */
final class TriggerInvocation
{
    /**
     * @param list<string> $arguments Argument texts as the function receives them
     * @throws InvalidStructure
     */
    public function __construct(public readonly QualifiedName $function, public readonly array $arguments = [])
    {
        Collections::strings($arguments);
        if (count($function->parts) > 3) {
            throw new InvalidStructure('A trigger function name has at most three parts.');
        }
    }
}
