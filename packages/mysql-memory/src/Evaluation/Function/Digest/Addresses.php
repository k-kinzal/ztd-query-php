<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Digest;

use Closure;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The network address functions: INET_ATON, INET_NTOA, INET6_ATON, INET6_NTOA, IS_IPV4, IS_IPV6, IS_IPV4_COMPAT and IS_IPV4_MAPPED.
 *
 * INET_ATON reads up to four dot-separated numbers of at most 255, the last filling the bytes the
 * short form leaves out (`127.1` is 127.0.0.1) and an empty one counting as 0. INET6_ATON and the
 * IS_IPV tests read an IPv4 address of exactly four numbers of one to three digits, or an IPv6
 * address of groups of one to four hexadecimal digits, `::` standing for at least one group and an
 * IPv4 address ending it. INET6_NTOA and the IS_IPV4_ tests read a binary string of 4 or 16 bytes;
 * INET6_NTOA writes the first longest run of zero groups as `::`, and an IPv4-compatible address
 * (`::a.b.c.d` whose seventh group is not zero) or IPv4-mapped one (`::ffff:a.b.c.d`) with the IPv4
 * address dotted. A value the conversions refuse is NULL with ER_WRONG_VALUE_FOR_TYPE, which quotes
 * the argument as the server writes the expression. MySQL 5.6 and 5.7 answer the IS_IPV tests of
 * NULL with 0 (verified on live 5.6.51, 5.7.44 and 8.4 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/miscellaneous-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Addresses
{
    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('INET_ATON', 1, 1, $this->inetAton(...)),
            new Routine('INET_NTOA', 1, 1, $this->inetNtoa(...)),
            new Routine('INET6_ATON', 1, 1, $this->inet6Aton(...)),
            new Routine('INET6_NTOA', 1, 1, $this->inet6Ntoa(...)),
            new Routine('IS_IPV4', 1, 1, fn (Frame $f, array $a): ?int => $this->test($f, $a[0], fn (string $t): bool => $this->ipv4($t) !== null)),
            new Routine('IS_IPV6', 1, 1, fn (Frame $f, array $a): ?int => $this->test($f, $a[0], fn (string $t): bool => $this->ipv6($t) !== null)),
            new Routine('IS_IPV4_COMPAT', 1, 1, fn (Frame $f, array $a): ?int => $this->test($f, $a[0], fn (string $t): bool => $this->binary($a[0]) && strlen($t) === 16 && str_starts_with($t, str_repeat("\0", 12)) && !in_array(substr($t, 12), ["\0\0\0\0", "\0\0\0\1"], true))),
            new Routine('IS_IPV4_MAPPED', 1, 1, fn (Frame $f, array $a): ?int => $this->test($f, $a[0], fn (string $t): bool => $this->binary($a[0]) && strlen($t) === 16 && str_starts_with($t, str_repeat("\0", 10) . "\xFF\xFF"))),
        ];
    }

    /**
     * Answers an IS_IPV test of the text of an argument: 1 or 0, and for NULL NULL, or 0 in MySQL 5.6 and 5.7.
     *
     * @param Closure(string): bool $test
     */
    public function test(Frame $frame, Evaluable $argument, Closure $test): ?int
    {
        $text = Convert::toText($argument->evaluate($frame), $argument->domain());
        if ($text === null) {
            return in_array($frame->context->modes->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? 0 : null;
        }

        return $test($text) ? 1 : 0;
    }

    /**
     * Tells whether an argument is a binary string.
     */
    public function binary(Evaluable $argument): bool
    {
        $domain = $argument->domain();

        return $domain->kind === Kind::String && $domain->collation === Collation::binary();
    }

    /**
     * Answers the argument of a call of one argument as the server writes it in a message.
     */
    public function argument(string $call): string
    {
        $open = strpos($call, '(');
        $close = strrpos($call, ')');

        return $open === false || $close === false ? $call : substr($call, $open + 1, $close - $open - 1);
    }

    /**
     * INET_ATON(text): the number of an IPv4 address, short forms included; NULL with a warning for another text.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws \MySqlMemory\Error\SqlError When the warning is raised as an error
     */
    public function inetAton(Frame $frame, array $arguments, Domain $result, string $call): ?int
    {
        $text = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        if ($text === null) {
            return null;
        }
        $number = $this->number($text);
        if ($number === null) {
            $frame->context->warning(DataError::WrongValueForType, 'string', $this->argument($call), 'inet_aton');
        }

        return $number;
    }

    /**
     * Reads an IPv4 address in the forms INET_ATON takes, or answers null.
     */
    public function number(string $text): ?int
    {
        if ($text === '' || str_ends_with($text, '.') || preg_match('/\A[0-9.]*\z/', $text) !== 1) {
            return null;
        }
        $parts = explode('.', $text);
        if (count($parts) > 4) {
            return null;
        }
        $number = 0;
        foreach ($parts as $part) {
            if (strlen(ltrim($part, '0')) > 3 || (int) $part > 255) {
                return null;
            }
            $number = ($number << 8) + (int) $part;
        }
        $last = $number & 255;

        return (($number >> 8) << (8 * (5 - count($parts)))) + $last;
    }

    /**
     * INET_NTOA(number): the dotted IPv4 address of a number from 0 to 4294967295; NULL with a warning for another number.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws \MySqlMemory\Error\SqlError When the warning is raised as an error
     */
    public function inetNtoa(Frame $frame, array $arguments, Domain $result, string $call): ?string
    {
        $domain = $arguments[0]->domain();
        $number = Convert::toInteger($arguments[0]->evaluate($frame), $domain, $frame->context);
        if ($number === null) {
            return null;
        }
        if ($number < 0 || $number > 4294967295) {
            $frame->context->warning(DataError::WrongValueForType, 'integer', $this->argument($call), 'inet_ntoa');

            return null;
        }

        return (new Hashes())->written(implode('.', [$number >> 24, ($number >> 16) & 255, ($number >> 8) & 255, $number & 255]), $result);
    }

    /**
     * Reads an IPv4 address of four numbers of one to three digits into its 4 bytes, or answers null.
     */
    public function ipv4(string $text): ?string
    {
        if (preg_match('/\A([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\z/', $text, $parts) !== 1) {
            return null;
        }
        $bytes = '';
        foreach (array_slice($parts, 1) as $part) {
            if ((int) $part > 255) {
                return null;
            }
            $bytes .= chr((int) $part);
        }

        return $bytes;
    }

    /**
     * Reads an IPv6 address into its 16 bytes, or answers null.
     */
    public function ipv6(string $text): ?string
    {
        $halves = explode('::', $text);
        if (count($halves) > 2) {
            return null;
        }
        $head = $this->groups($halves[0], count($halves) === 1);
        $tail = count($halves) === 2 ? $this->groups($halves[1], true) : '';
        if ($head === null || $tail === null) {
            return null;
        }
        $bytes = strlen($head) + strlen($tail);
        if (count($halves) === 1) {
            return $bytes === 16 ? $head : null;
        }

        return $bytes <= 14 ? $head . str_repeat("\0", 16 - $bytes) . $tail : null;
    }

    /**
     * Reads the colon-separated groups of a part of an IPv6 address into bytes, an IPv4 address allowed last, or answers null.
     */
    public function groups(string $part, bool $last): ?string
    {
        if ($part === '') {
            return '';
        }
        $groups = explode(':', $part);
        $bytes = '';
        foreach ($groups as $index => $group) {
            if ($last && $index === count($groups) - 1 && str_contains($group, '.')) {
                $ipv4 = $this->ipv4($group);
                if ($ipv4 === null) {
                    return null;
                }
                $bytes .= $ipv4;
                continue;
            }
            if (preg_match('/\A[0-9a-fA-F]{1,4}\z/', $group) !== 1) {
                return null;
            }
            $bytes .= pack('n', hexdec($group));
        }

        return $bytes;
    }

    /**
     * INET6_ATON(text): the 4 or 16 bytes of an IPv4 or IPv6 address; NULL with a warning for another text.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws \MySqlMemory\Error\SqlError When the warning is raised as an error
     */
    public function inet6Aton(Frame $frame, array $arguments, Domain $result, string $call): ?string
    {
        $text = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        if ($text === null) {
            return null;
        }
        $bytes = $this->ipv4($text) ?? $this->ipv6($text);
        if ($bytes === null) {
            $frame->context->warning(DataError::WrongValueForType, 'string', $this->argument($call), 'inet6_aton');
        }

        return $bytes;
    }

    /**
     * INET6_NTOA(bytes): the text of an address of 4 or 16 bytes of a binary string; NULL with a warning for another value.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws \MySqlMemory\Error\SqlError When the warning is raised as an error
     */
    public function inet6Ntoa(Frame $frame, array $arguments, Domain $result, string $call): ?string
    {
        $bytes = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        if ($bytes === null) {
            return null;
        }
        if (!$this->binary($arguments[0]) || (strlen($bytes) !== 4 && strlen($bytes) !== 16)) {
            $frame->context->warning(DataError::WrongValueForType, 'string', $this->argument($call), 'inet6_ntoa');

            return null;
        }

        return (new Hashes())->written($this->address($bytes), $result);
    }

    /**
     * Writes the 4 or 16 bytes of an address as INET6_NTOA does.
     */
    public function address(string $bytes): string
    {
        if (strlen($bytes) === 4) {
            return implode('.', array_map(ord(...), str_split($bytes)));
        }
        if (str_starts_with($bytes, str_repeat("\0", 10) . "\xFF\xFF")) {
            return '::ffff:' . $this->address(substr($bytes, 12));
        }
        if (str_starts_with($bytes, str_repeat("\0", 12)) && substr($bytes, 12, 2) !== "\0\0") {
            return '::' . $this->address(substr($bytes, 12));
        }
        $groups = [];
        for ($i = 0; $i < 16; $i += 2) {
            $groups[] = (ord($bytes[$i]) << 8) | ord($bytes[$i + 1]);
        }
        [$start, $length] = [-1, 0];
        for ($i = 0; $i < 8; $i++) {
            $run = 0;
            while ($i + $run < 8 && $groups[$i + $run] === 0) {
                $run++;
            }
            if ($run > $length) {
                [$start, $length] = [$i, $run];
            }
        }
        $hex = array_map(dechex(...), $groups);
        if ($length === 0) {
            return implode(':', $hex);
        }

        return implode(':', array_slice($hex, 0, $start)) . '::' . implode(':', array_slice($hex, $start + $length));
    }
}
