<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Type;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A type that is one of several known types, decided by values at run time.
 *
 * @visibility public
 * @example Recording two possible types
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $table = $semantics->analyze('CREATE TABLE t (a INTEGER, b TEXT)')->declarations()[0];
 *     $choice = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Choice([$table->columns[0]->type, $table->columns[1]->type]);
 *     count($choice->alternatives) // => 2
 */
final class Choice implements TypeFact
{
    use Snapshot;

    /**
     * @var list<TypeDescriptor> The possible types, without duplicates, in rule order
     */
    public readonly array $alternatives;

    /**
     * @param list<TypeDescriptor> $alternatives At least two possible types
     */
    public function __construct(array $alternatives)
    {
        $this->alternatives = Check::listOf($alternatives, TypeDescriptor::class, 'A type choice holds at least two type descriptors.', 2);
    }
}
