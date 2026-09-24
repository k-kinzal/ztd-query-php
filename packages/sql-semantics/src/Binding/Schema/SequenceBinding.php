<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\Relation\IdentityActions;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Relation\Identity;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Column\SequenceOptions;

/**
 * Classifies sequence values without coercing their arbitrary-precision integers.
 *
 * @visibility SqlSemantics
 */
final class SequenceBinding
{
    /**
     * Reads every sequence option of an identity column; an option given twice conflicts.
     *
     * @param list<Node> $attributes
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function read(array $attributes, Scope $scope): SequenceOptions
    {
        $context = $scope->queries ?? throw new UnclassifiedSql('Sequence options require a binding context.');
        $values = [];
        foreach ($attributes as $attribute) {
            foreach (Tree::outer($attribute, ['SeqOptElem']) as $option) {
                $bound = IdentityActions::option($option, $scope, $context);
                $key = self::key($bound);
                if (array_key_exists($key, $values)) {
                    throw new InvalidSql(InputViolation::SequenceOption, $option);
                }
                $values[$key] = $bound;
            }
        }
        try {
            return self::options($values);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::SequenceOption, $attributes[0] ?? new Node('sequence_options', 0, []), $error);
        }
    }

    /**
     * Names the sequence parameter an option sets, so that conflicting options share a name.
     */
    public static function key(Identity\SequenceValueChange|Identity\SequenceFlag|Identity\SetSequenceName|Identity\SequenceStorage|Identity\SetSequenceOwner|Identity\RestartIdentity $option): string
    {
        return match (true) {
            $option instanceof Identity\SequenceValueChange => $option->attribute->name,
            $option instanceof Identity\SequenceFlag => match ($option) {
                Identity\SequenceFlag::Cycle, Identity\SequenceFlag::NoCycle => 'cycle',
                Identity\SequenceFlag::NoMinValue => Identity\SequenceAttribute::MinValue->name,
                Identity\SequenceFlag::NoMaxValue => Identity\SequenceAttribute::MaxValue->name,
                Identity\SequenceFlag::Logged, Identity\SequenceFlag::Unlogged => 'logged',
            },
            $option instanceof Identity\SetSequenceName => 'name',
            $option instanceof Identity\SequenceStorage => 'storage',
            $option instanceof Identity\SetSequenceOwner => 'owner',
            $option instanceof Identity\RestartIdentity => 'restart',
        };
    }

    /**
     * Assembles the declared options; NO MINVALUE and NO MAXVALUE keep the default bounds.
     *
     * @param array<string, Identity\SequenceValueChange|Identity\SequenceFlag|Identity\SetSequenceName|Identity\SequenceStorage|Identity\SetSequenceOwner|Identity\RestartIdentity> $values
     * @throws InvalidStructure
     */
    public static function options(array $values): SequenceOptions
    {
        $number = static fn (Identity\SequenceAttribute $attribute): ?\SqlSemantics\Model\Scalar\Value\Literal => ($values[$attribute->name] ?? null) instanceof Identity\SequenceValueChange ? $values[$attribute->name]->value : null;
        $cycle = $values['cycle'] ?? null;
        $logged = $values['logged'] ?? null;
        $name = $values['name'] ?? null;
        $storage = $values['storage'] ?? null;
        $owner = $values['owner'] ?? null;
        $restart = $values['restart'] ?? null;
        return new SequenceOptions(
            $number(Identity\SequenceAttribute::Start),
            $number(Identity\SequenceAttribute::Increment),
            $number(Identity\SequenceAttribute::MinValue),
            $number(Identity\SequenceAttribute::MaxValue),
            $number(Identity\SequenceAttribute::Cache),
            $cycle === null ? null : $cycle === Identity\SequenceFlag::Cycle,
            $storage instanceof Identity\SequenceStorage ? $storage->type : null,
            $name instanceof Identity\SetSequenceName ? $name->name : null,
            $logged === null ? null : $logged === Identity\SequenceFlag::Logged,
            $owner instanceof Identity\SetSequenceOwner ? $owner : null,
            $restart instanceof Identity\RestartIdentity ? $restart : null,
        );
    }
}
