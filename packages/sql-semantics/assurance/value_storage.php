<?php

declare(strict_types=1);

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\Parser;
use PhpParser\PrettyPrinter\Standard;

/**
 * Reduces an indexed write to its underlying storage location.
 */
function storageTarget(Expr $expression): ?string
{
    while ($expression instanceof Expr\ArrayDimFetch) {
        $expression = $expression->var;
    }
    if ($expression instanceof Expr\Variable && is_string($expression->name)) {
        return '$' . $expression->name;
    }
    if ($expression instanceof Expr\PropertyFetch && $expression->var instanceof Expr\Variable
        && $expression->var->name === 'this' && $expression->name instanceof Identifier) {
        return '$this->' . $expression->name->toString();
    }

    return null;
}

function writesLocation(Expr $expression, string $location): bool
{
    if ($expression instanceof Expr\Array_ || $expression instanceof Expr\List_) {
        foreach ($expression->items as $item) {
            if ($item !== null && writesLocation($item->value, $location)) {
                return true;
            }
        }

        return false;
    }

    return storageTarget($expression) === $location;
}

function storageWrites(NodeFinder $finder, array $body, string $location): array
{
    return $finder->find($body, static function (Node $node) use ($location): bool {
        if ($node instanceof Expr\Assign || $node instanceof Expr\AssignOp || $node instanceof Expr\AssignRef
            || $node instanceof Expr\PreInc || $node instanceof Expr\PreDec || $node instanceof Expr\PostInc || $node instanceof Expr\PostDec) {
            return writesLocation($node->var, $location);
        }
        if ($node instanceof Stmt\Unset_) {
            return in_array($location, array_map(storageTarget(...), $node->vars), true);
        }
        if ($node instanceof Stmt\Foreach_) {
            return writesLocation($node->valueVar, $location)
                || ($node->keyVar !== null && writesLocation($node->keyVar, $location));
        }
        if ($node instanceof Expr\FuncCall && $node->name instanceof Name
            && in_array($node->name->toString(), ['array_pop', 'array_push'], true)) {
            return isset($node->args[0]) && $node->args[0] instanceof Node\Arg
                && storageTarget($node->args[0]->value) === $location;
        }

        return false;
    });
}

function checkValueStorage(
    Stmt\Class_ $class,
    array $propertyDomain,
    array $contracts,
    string $short,
    string $prefix,
    string $checkClass,
    Parser $parser,
    Standard $printer,
    NodeFinder $finder,
    array $checkedBoundaries,
): void {
    $lists = array_keys(array_filter($propertyDomain, static fn (array $types): bool => $types === ['array']));
    $explicit = [];
    foreach ($class->getProperties() as $property) {
        foreach ($property->props as $field) {
            requireShape($field->default === null, "Unexpected readonly field initializer in $short");
            $explicit[] = $field->name->toString();
        }
    }
    requireShape(
        array_diff($lists, array_keys($contracts)) === [] && count($lists) === count($contracts),
        "List storage coverage differs in $short"
    );

    $constructor = $class->getMethod('__construct');
    if ($constructor === null) {
        requireShape($explicit === [], "An explicit value field needs its constructor in $short");

        return;
    }
    $body = $constructor->stmts ?? [];
    $nonlinearExit = $finder->findFirst($body, static fn (Node $node): bool => $node instanceof Stmt\Return_
        || $node instanceof Stmt\TryCatch || $node instanceof Stmt\Goto_ || $node instanceof Expr\Yield_
        || $node instanceof Expr\YieldFrom);
    requireShape($nonlinearExit === null, "A constructor has an unchecked exit in $short");
    $top = [];
    foreach ($body as $index => $statement) {
        if ($statement instanceof Stmt\Expression && $statement->expr instanceof Expr\Assign) {
            $name = storageTarget($statement->expr->var);
            if ($name !== null) {
                $top[$name] = [$index, $statement->expr];
            }
        }
    }

    foreach ($explicit as $property) {
        $location = '$this->' . $property;
        [, $write] = $top[$location] ?? [null, null];
        requireShape(
            $write instanceof Expr\Assign && $write->var instanceof Expr\PropertyFetch
                && storageWrites($finder, $body, $location) === [$write],
            "Value field needs one unconditional initialization in $short::$property"
        );
    }

    foreach ($contracts as $property => $rule) {
        $location = '$this->' . $property;
        requireShape(storageWrites($finder, $body, '$' . $property) === [], "List parameter is reassigned in $short::$property");
        [$index, $write] = $top[$location] ?? [null, null];
        requireShape($write instanceof Expr\Assign && $write->var instanceof Expr\PropertyFetch
            && storageWrites($finder, $body, $location) === [$write], "List needs one unconditional property write in $short::$property");

        if ($rule === 'closed-scalar-copy') {
            checkScalarCopy($body, $prefix, $checkClass, $parser, $printer);

            continue;
        }

        if (in_array($rule, ['postgres-integer-membership-copy', 'mysql-integer-membership-copy'], true)) {
            requireShape($short === 'Predicate\\IntegerMembership' && $property === 'alternatives'
                && in_array('Predicate/IntegerMembership::__construct', $checkedBoundaries, true)
                && $write->expr instanceof Expr\Variable && $write->expr->name === 'terms',
                'Signed membership alternatives must come from the verified detached copy.');

            continue;
        }

        if ($rule === 'postgres-index-target-copy') {
            requireShape($short === 'Schema\\Index\\DropIndex' && $property === 'targets'
                && in_array('Schema/Index/DropIndex::__construct', $checkedBoundaries, true)
                && $write->expr instanceof Expr\Variable && $write->expr->name === 'copy',
                'PostgreSQL index removal targets must come from the verified detached copy.');

            continue;
        }

        if ($rule === 'sqlite-append-ordinary-slot-copy' || $rule === 'sqlite-append-generated-slot-copy') {
            requireShape(in_array($role = str_replace('\\', '/', $short), ['Schema/Alter/AddColumn', 'Schema/Alter/AddGeneratedColumn'], true)
                && $property === 'bindings' && in_array($role . '::__construct', $checkedBoundaries, true)
                && $write->expr instanceof Expr\MethodCall && $write->expr->name instanceof Identifier
                && $write->expr->name->toString() === 'bindings',
                'SQLite appended bindings must be the detached projection of the verified operands.');

            continue;
        }

        if ($rule === 'sqlite-index-slot-copy') {
            requireShape($short === 'Schema\\Index\\CreateIndex' && $property === 'bindings'
                && in_array('Schema/Index/CreateIndex::__construct', $checkedBoundaries, true)
                && $write->expr instanceof Expr\Variable && $write->expr->name === 'resolvedNames',
                'SQLite index definitions require the verified detached operand slot map.');

            continue;
        }

        if ($rule === 'postgres-index-slot-copy') {
            requireShape($short === 'Schema\\Index\\Btree\\CreateIndex' && $property === 'bindings'
                && in_array('Schema/Index/Btree/CreateIndex::__construct', $checkedBoundaries, true)
                && $write->expr instanceof Expr\Variable && $write->expr->name === 'resolvedFields',
                'PostgreSQL B-tree definitions require the checked detached operand slot map.');

            continue;
        }

        if ($rule === 'sqlite-definition-slot-copy') {
            requireShape($short === 'Schema\\Predicate\\DefinitionSource' && $property === 'columns'
                && in_array('Schema/Predicate/DefinitionSource::__construct', $checkedBoundaries, true)
                && $write->expr instanceof Expr\Variable && $write->expr->name === 'bindings',
                'SQLite definition source requires the verified detached slot map.');

            continue;
        }

        if ($rule === 'sqlite-trigger-action-copy') {
            requireShape($short === 'Schema\\Trigger\\CreateTrigger' && $property === 'program'
                && in_array('Schema/Trigger/CreateTrigger::__construct', $checkedBoundaries, true)
                && $write->expr instanceof Expr\Variable && $write->expr->name === 'actions',
                'SQLite trigger action order requires the verified closed copy.');

            continue;
        }

        if ($rule === 'sqlite-trigger-writable-copy') {
            requireShape($short === 'Schema\\Trigger\\TriggerDestination' && $property === 'writable'
                && in_array('Schema/Trigger/TriggerDestination::__construct', $checkedBoundaries, true)
                && $write->expr instanceof Expr\Variable && $write->expr->name === 'positions',
                'SQLite writable trigger positions must come from the verified detached copy.');

            continue;
        }

        if ($rule === 'native-slot-copy') {
            requireShape($short === 'TableSource' && $property === 'columnTypes'
                && in_array('TableSource::__construct', $checkedBoundaries, true)
                && $write->expr instanceof Expr\Variable && $write->expr->name === 'owned',
                'Native slot types must come from the verified detached copy.');

            continue;
        }

        if ($rule === 'native-assignment-copy') {
            requireShape($short === 'Mutation\\Update' && $property === 'assignments'
                && in_array('Mutation/Update::__construct', $checkedBoundaries, true)
                && $write->expr instanceof Expr\Variable && $write->expr->name === 'checked',
                'Native UPDATE assignments must come from the verified detached copy.');

            continue;
        }

        if ($rule === 'native-fk-add-replaced-copy' || $rule === 'native-fk-exchange-replaced-copy') {
            $role = str_replace('\\', '/', $short);
            $expected = $rule === 'native-fk-add-replaced-copy' ? 'Schema/ForeignKey/Add/AddForeignKeys' : 'Schema/ForeignKey/Exchange/AlterForeignKeys';
            $projection = $rule === 'native-fk-add-replaced-copy'
                ? $write->expr instanceof Expr\MethodCall && $write->expr->name instanceof Identifier && $write->expr->name->toString() === 'check'
                : $write->expr instanceof Expr\Variable && $write->expr->name === 'replaced';
            requireShape($role === $expected && $property === 'removedIndexes'
                && in_array($role . '::__construct', $checkedBoundaries, true) && $projection,
                'Replaced native keys must come from the verified physical allocation.');

            continue;
        }

        if ($rule === 'native-index-removal-target-copy') {
            requireShape($short === 'Schema\\Index\\DropIndexes' && $property === 'targets'
                && in_array('Schema/Index/DropIndexes::__construct', $checkedBoundaries, true)
                && $write->expr instanceof Expr\Variable && $write->expr->name === 'identities',
                'Native batch index removals must contain the verified index identity projection.');

            continue;
        }

        if ($rule === 'native-fk-exchange-actions-copy') {
            requireShape($short === 'Schema\\ForeignKey\\Exchange\\AlterForeignKeys' && $property === 'actions'
                && in_array('Schema/ForeignKey/Exchange/AlterForeignKeys::__construct', $checkedBoundaries, true)
                && $write->expr instanceof Expr\Variable && $write->expr->name === 'checked',
                'Native foreign-key exchange actions must come from the verified detached copy.');

            continue;
        }

        if ($rule === 'native-foreign-key-removal-copy') {
            requireShape($short === 'Schema\\ForeignKey\\DropForeignKeys' && $property === 'actions'
                && in_array('Schema/ForeignKey/DropForeignKeys::__construct', $checkedBoundaries, true)
                && $write->expr instanceof Expr\Variable && $write->expr->name === 'checked',
                'Native foreign-key actions must come from the verified detached copy.');

            continue;
        }

        if ($rule === 'native-drop-target-copy') {
            requireShape($short === 'Schema\\DropTable' && $property === 'targets'
                && in_array('Schema/DropTable::__construct', $checkedBoundaries, true)
                && $write->expr instanceof Expr\Variable && $write->expr->name === 'checked',
                'Native DROP targets must come from the verified detached copy.');

            continue;
        }

        if ($rule === 'postgres-unique-list-copy') {
            requireShape($short === 'Schema\\CreateTable' && $property === 'uniqueKeys'
                && in_array('Schema/CreateTable::__construct', $checkedBoundaries, true)
                && $write->expr instanceof Expr\Variable && $write->expr->name === 'retained',
                'The unique key list must come from the verified native normalization of detached values.');

            continue;
        }

        if ($rule === 'postgres-primary-column-copy') {
            requireShape($short === 'Schema\\CreateTable' && $property === 'columns'
                && in_array('Schema/CreateTable::__construct', $checkedBoundaries, true)
                && $write->expr instanceof Expr\Variable && $write->expr->name === 'checked',
                'The PostgreSQL table columns must come from the checked detached copy with applied primary key nullability.');

            continue;
        }

        if ($rule === 'ordered-table-key-copy') {
            requireShape(
                $short === 'Schema\\CreateTable' && $property === 'keys'
                && $write->expr instanceof Expr\Variable && $write->expr->name === 'orderedKeys',
                'The table key list must come from the checked layout.'
            );
            $inputs = storageWrites($finder, $body, '$orderedKeys');
            $expected = '<?php [$orderedKeys, $primaryKey] = (new \\' . $prefix
                . 'Schema\\Key\\TableKeyLayout())->check($keys, $checked, $withoutRowid);';
            requireShape(
                count($inputs) === 1 && $inputs[0] instanceof Expr\Assign
                && $printer->prettyPrint([$inputs[0]]) === $printer->prettyPrint([$parser->parse($expected)[0]->expr]),
                'The table key layout must detach the ordered list.'
            );

            continue;
        }

        [$type, $minimum] = $rule;
        $source = $write->expr;
        if ($source instanceof Expr\Variable) {
            $local = storageTarget($source);
            [$sourceIndex, $localWrite] = $top[$local] ?? [null, null];
            requireShape(
                $localWrite instanceof Expr\Assign && $localWrite->var instanceof Expr\Variable
                && $sourceIndex < $index && storageWrites($finder, $body, $local) === [$localWrite],
                "List was not assigned from one checked local in $short::$property"
            );
            $source = $localWrite->expr;
        }

        requireShape(
            $source instanceof Expr\StaticCall
            && $source->class instanceof Name\FullyQualified && $source->class->toString() === $checkClass
            && $source->name instanceof Identifier && $source->name->toString() === 'listOf',
            "List storage is not detached by the checked copy in $short::$property"
        );
        $args = $source->getArgs();
        requireShape(
            count($args) === ($minimum === 0 ? 3 : 4)
            && array_filter($args, static fn (Node\Arg $arg): bool => $arg->name !== null) === []
            && $args[0]->value instanceof Expr\Variable && $args[0]->value->name === $property
            && $args[1]->value instanceof Expr\ClassConstFetch && $args[1]->value->class instanceof Name\FullyQualified
            && $args[1]->value->class->toString() === $prefix . str_replace('/', '\\', $type)
            && $args[1]->value->name instanceof Identifier && $args[1]->value->name->toString() === 'class'
            && $args[2]->value instanceof Scalar\String_
            && ($minimum === 0 || ($args[3]->value instanceof Scalar\Int_ && $args[3]->value->value === $minimum)),
            "Checked list member domain differs in $short::$property"
        );
    }
}

function checkScalarCopy(array $body, string $prefix, string $checkClass, Parser $parser, Standard $printer): void
{
    $expected = <<<'PHP'
<?php
\CHECK::input(array_is_list($values) && $values !== [] && count($values) <= 2000, 'A VALUES row needs an ordered list of 1 to 2000 operands.');
$checker = new \IR\Rows\ValueRowOperand();
$closed = [];
$pending = [];
foreach ($values as $value) {
    \CHECK::input(is_object($value), 'A VALUES row needs a scalar operand independent of tables.');
    $operand = $checker->checked($value);
    $closed[] = $operand;
    $pending[] = [$operand, 0, 0];
}
(new \IR\Predicate\ExpressionScope())->check([], $pending);
$this->values = $closed;
PHP;
    $expected = str_replace(['\\CHECK', '\\IR\\'], ['\\' . $checkClass, '\\' . $prefix], $expected);
    requireShape(
        $printer->prettyPrint($body) === $printer->prettyPrint($parser->parse($expected)),
        'Checked scalar copy differs in Rows/ValueRow'
    );
}
