<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Replication;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\DefinitionArguments;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\DefinitionElement;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Replication\Subscription as Operand;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Reads the documented subscription options a command accepts, each at most once and with a value of its form.
 * @visibility SqlSemantics
 */
final class SubscriptionOptionReader
{
    /**
     * Rejects names outside the allowed set and repeated names, then reads each value.
     * @param list<Operand\SubscriptionParameter> $allowed
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function read(?Node $definition, array $allowed, QueryContext $context): Operand\SubscriptionOptions
    {
        if ($definition === null) {
            return new Operand\SubscriptionOptions();
        }
        $named = DefinitionElement::named(DefinitionElement::list($definition, $context), array_map(static fn (Operand\SubscriptionParameter $parameter): string => $parameter->value, $allowed), false);
        $flag = static fn (Operand\SubscriptionParameter $parameter): ?bool => isset($named[$parameter->value]) ? self::boolean($named[$parameter->value], $context) : null;
        $text = static fn (Operand\SubscriptionParameter $parameter): ?string => isset($named[$parameter->value]) ? strtolower(self::text($named[$parameter->value], $context)) : null;
        $synchronous = $text(Operand\SubscriptionParameter::SynchronousCommit);
        $origin = $text(Operand\SubscriptionParameter::Origin);
        try {
            return new Operand\SubscriptionOptions(
                $flag(Operand\SubscriptionParameter::Connect),
                $flag(Operand\SubscriptionParameter::Enabled),
                $flag(Operand\SubscriptionParameter::CreateSlot),
                isset($named['slot_name']) ? self::slot($named['slot_name'], $context) : null,
                $flag(Operand\SubscriptionParameter::CopyData),
                $synchronous === null ? null : self::synchronous($synchronous, $named['synchronous_commit']->source),
                $flag(Operand\SubscriptionParameter::Refresh),
                $flag(Operand\SubscriptionParameter::Binary),
                isset($named['streaming']) ? self::streaming($named['streaming'], $context) : null,
                $flag(Operand\SubscriptionParameter::TwoPhase),
                $flag(Operand\SubscriptionParameter::DisableOnError),
                $flag(Operand\SubscriptionParameter::PasswordRequired),
                $flag(Operand\SubscriptionParameter::RunAsOwner),
                $flag(Operand\SubscriptionParameter::Failover),
                $origin === null ? null : (Operand\OriginFilter::tryFrom($origin) ?? throw new InvalidSql(InputViolation::DefinitionArgument, $definition)),
            );
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::DefinitionArgument, $definition, $error);
        }
    }

    /**
     * A Boolean option written without a value is true.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function boolean(DefinitionElement $element, QueryContext $context): bool
    {
        return $element->argument === null || DefinitionArguments::boolean($element->argument, $context);
    }

    /**
     * The text of a value that is required.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function text(DefinitionElement $element, QueryContext $context): string
    {
        return DefinitionArguments::text($element->argument ?? throw new InvalidSql(InputViolation::DefinitionArgument, $element->source), $context);
    }

    /**
     * NONE in lower case dissociates the slot; any other text names it.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function slot(DefinitionElement $element, QueryContext $context): string|Operand\NoSlot
    {
        $name = self::text($element, $context);
        return $name === 'none' ? Operand\NoSlot::None : $name;
    }

    /**
     * The synchronous_commit levels, including the Boolean spellings of on and off.
     * @throws InvalidSql
     */
    public static function synchronous(string $value, Node $source): Operand\SynchronousCommit
    {
        return match ($value) {
            'true', 'yes', '1', 'on' => Operand\SynchronousCommit::On,
            'false', 'no', '0', 'off' => Operand\SynchronousCommit::Off,
            default => Operand\SynchronousCommit::tryFrom($value) ?? throw new InvalidSql(InputViolation::DefinitionArgument, $source),
        };
    }

    /**
     * A Boolean or parallel; streaming without a value is on.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function streaming(DefinitionElement $element, QueryContext $context): Operand\StreamingMode
    {
        if ($element->argument === null) {
            return Operand\StreamingMode::On;
        }
        if (Tree::child($element->argument, ['NumericOnly']) === null && strtolower(DefinitionArguments::text($element->argument, $context)) === 'parallel') {
            return Operand\StreamingMode::Parallel;
        }
        return DefinitionArguments::boolean($element->argument, $context) ? Operand\StreamingMode::On : Operand\StreamingMode::Off;
    }

    /**
     * The SKIP position: NONE clears it, otherwise a nonzero X/X hexadecimal position in canonical spelling.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function lsn(Node $definition, QueryContext $context): ?string
    {
        $named = DefinitionElement::named(DefinitionElement::list($definition, $context), ['lsn'], false);
        $element = $named['lsn'] ?? throw new InvalidSql(InputViolation::DefinitionRequirement, $definition);
        $text = self::text($element, $context);
        if ($text === 'none') {
            return null;
        }
        if (preg_match('/^([0-9a-fA-F]{1,8})\/([0-9a-fA-F]{1,8})$/D', $text, $parts) !== 1 || ltrim($parts[1] . $parts[2], '0') === '') {
            throw new InvalidSql(InputViolation::DefinitionArgument, $element->source);
        }
        return sprintf('%X/%X', hexdec($parts[1]), hexdec($parts[2]));
    }
}
