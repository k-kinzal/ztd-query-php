<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use InvalidArgumentException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Statement;
use SqlSemantics\Statement\StatementException;
use SqlSemantics\Statement\Traversal;
use Throwable;

/**
 * A statement built by replacing a value with another value of its role is either valid SQL that reads back as itself, or refused.
 *
 * One value of a generated statement is replaced with another value of the
 * same statement that shares one of its roles, so a finding reproduces from
 * its input alone. The replacement is a rewrite a consumer could write. It
 * may be refused: by the position, with an InvalidArgumentException, when
 * the value cannot occupy it; or by the statement, with a
 * StatementException, when the SQL it would write reads back as other SQL.
 * Anything else is a finding: a crash, a statement that does not hold the
 * value it was built with, or one whose SQL reads back as a different
 * statement.
 */
final class SpliceTarget
{
    public function __construct(private readonly Semantics $semantics, private readonly string $grammarVersion)
    {
    }

    /**
     * Replaces one value of the statement with a donated value of the same role.
     */
    public function verify(string $sql, string $input): void
    {
        $context = "Grammar: {$this->grammarVersion}\nInput (hex): " . bin2hex($input) . "\nSQL: {$sql}";
        try {
            $statement = $this->semantics->analyze($sql);
        } catch (Throwable $failure) {
            throw new Error("Analysis failed\n{$context}\nError: {$failure->getMessage()}", 0, $failure);
        }
        $values = array_slice(iterator_to_array(Traversal::walk($statement->command), false), 1);
        $seed = crc32($input);
        $target = $values === [] ? null : $values[$seed % count($values)];
        $donor = $target === null ? null : self::donor($values, $target, $seed >> 8);
        if ($target === null || $donor === null) {
            return;
        }
        $context .= "\nReplaced: " . $target::class . "\nWith: " . $donor::class;
        try {
            $rewritten = Traversal::rewrite($statement->command, static fn (Element $value): Element => $value === $target ? $donor : $value);
            $spliced = $statement->withCommand($rewritten);
        } catch (InvalidArgumentException | StatementException) {
            return;
        } catch (Throwable $failure) {
            throw new Error("Replacing a value failed instead of being refused\n{$context}\nError: " . $failure::class . ': ' . $failure->getMessage(), 0, $failure);
        }
        $this->check($spliced, $donor, $context);
    }

    /**
     * Requires an accepted statement to hold the donated value and to read back as itself.
     */
    private function check(Statement $spliced, Element $donor, string $context): void
    {
        $printed = $spliced->toString();
        $context .= "\nPrinted: {$printed}";
        if (!in_array($donor, iterator_to_array(Traversal::walk($spliced->command), false), true)) {
            throw new Error("The statement does not hold the value it was built with\n{$context}");
        }
        try {
            $again = $this->semantics->analyze($printed);
        } catch (Throwable $failure) {
            throw new Error("An accepted statement does not analyze\n{$context}\nError: {$failure->getMessage()}", 0, $failure);
        }
        if ($again->toString() !== $printed) {
            throw new Error("An accepted statement reads back as other SQL\n{$context}\nAgain: {$again->toString()}");
        }
    }

    /**
     * Picks another value of the statement that shares one of the roles of the target.
     *
     * @param list<Element> $values
     */
    private static function donor(array $values, Element $target, int $seed): ?Element
    {
        $roles = self::roles($target);
        $donors = array_values(array_filter($values, static fn (Element $value): bool => $value !== $target && array_intersect(self::roles($value), $roles) !== []));

        return $donors === [] ? null : $donors[$seed % count($donors)];
    }

    /**
     * Lists the role interfaces of a value, in a stable order.
     *
     * @return list<string>
     */
    private static function roles(Element $value): array
    {
        $roles = array_values(array_filter(class_implements($value), static fn (string $interface): bool => str_contains($interface, '\\Role\\')));
        sort($roles);

        return $roles;
    }
}
