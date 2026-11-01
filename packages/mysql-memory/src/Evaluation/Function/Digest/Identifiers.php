<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Digest;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use WeakMap;

/**
 * The identifier functions: UUID, UUID_SHORT, UUID_TO_BIN, BIN_TO_UUID and IS_UUID.
 *
 * UUID() is a version 1 UUID: the time in 100-nanosecond intervals since 15 October 1582, a clock
 * sequence and a node, here random for each server; consecutive calls never repeat it.
 * UUID_SHORT() starts at `(server_id & 255) << 56 | startup time << 24` and counts up by one for
 * each call; the emulator takes the startup time when the server first answers UUID_SHORT().
 * UUID_TO_BIN reads a UUID of 32 hexadecimal digits, with or without the dashes and braces of the
 * string form; BIN_TO_UUID reads 16 bytes. Both move the time-high part first when the swap flag is
 * true, and refuse another value with ER_WRONG_VALUE_FOR_TYPE, which quotes at most 128 bytes of it.
 * IS_UUID tells whether the bytes of a string are a UUID UUID_TO_BIN reads (verified on a live 8.4
 * server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/miscellaneous-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Identifiers
{
    /**
     * The 100-nanosecond intervals from 15 October 1582 to the Unix epoch.
     */
    public const GREGORIAN_OFFSET = 0x01B21DD213814000;

    /**
     * The state of the generators of each server, by its global variables: the node, the clock sequence, the last time and the next short UUID.
     *
     * @var WeakMap<object, array{string, int, int, int|null}>|null
     */
    private static ?WeakMap $generators = null;

    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('UUID', 0, 0, $this->uuid(...)),
            new Routine('UUID_SHORT', 0, 0, $this->uuidShort(...)),
            new Routine('UUID_TO_BIN', 1, 2, $this->uuidToBin(...)),
            new Routine('BIN_TO_UUID', 1, 2, $this->binToUuid(...)),
            new Routine('IS_UUID', 1, 1, fn (Frame $f, array $a): ?int => ($t = Convert::toText($a[0]->evaluate($f), $a[0]->domain())) === null ? null : ($this->bytes($t) === null ? 0 : 1)),
        ];
    }

    /**
     * Answers the state of the generators of the server a frame runs on.
     *
     * @return array{string, int, int, int|null}
     */
    public function generator(Frame $frame): array
    {
        $globals = $frame->context->variables->globals;
        $state = self::$generators[$globals] ?? null;
        if ($state === null) {
            $state = [random_bytes(6), random_int(0, 0x3FFF), 0, null];
            $this->store($frame, $state);
        }

        return $state;
    }

    /**
     * Keeps the state of the generators of the server a frame runs on.
     *
     * @param array{string, int, int, int|null} $state
     */
    public function store(Frame $frame, array $state): void
    {
        self::$generators ??= new WeakMap();
        self::$generators[$frame->context->variables->globals] = $state;
    }

    /**
     * UUID(): a version 1 UUID, later than any the server answered before.
     *
     * @param list<Evaluable> $arguments
     */
    public function uuid(Frame $frame, array $arguments, Domain $result): string
    {
        [$node, $sequence, $last, $short] = $this->generator($frame);
        $time = max((int) (microtime(true) * 10000000) + self::GREGORIAN_OFFSET, $last + 1);
        $this->store($frame, [$node, $sequence, $time, $short]);
        $text = sprintf('%08x-%04x-%04x-%04x-%s', $time & 0xFFFFFFFF, ($time >> 32) & 0xFFFF, (($time >> 48) & 0x0FFF) | 0x1000, $sequence | 0x8000, bin2hex($node));

        return (new Hashes())->written($text, $result);
    }

    /**
     * UUID_SHORT(): the next number of the server's sequence of short UUIDs.
     *
     * @param list<Evaluable> $arguments
     */
    public function uuidShort(Frame $frame, array $arguments, Domain $result): int
    {
        [$node, $sequence, $last, $short] = $this->generator($frame);
        $short ??= ((((int) $frame->context->variables->read('server_id')) & 255) << 56) + (((int) $frame->context->started) << 24);
        $this->store($frame, [$node, $sequence, $last, $short + 1]);

        return $short;
    }

    /**
     * Reads the 16 bytes of a UUID written as text, or answers null when the text is not a UUID.
     */
    public function bytes(string $text): ?string
    {
        if (strlen($text) === 38 && $text[0] === '{' && $text[37] === '}') {
            $text = substr($text, 1, 36);
            if (strlen($text) !== 36) {
                return null;
            }
        }
        if (strlen($text) === 36 && preg_match('/\A[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\z/', $text) === 1) {
            $text = str_replace('-', '', $text);
        }

        return strlen($text) === 32 && ctype_xdigit($text) ? (string) hex2bin($text) : null;
    }

    /**
     * Reads the swap flag of UUID_TO_BIN and BIN_TO_UUID: true when it is a number other than zero.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws \MySqlMemory\Error\SqlError When reading the flag raises a warning as an error
     */
    public function swapped(Frame $frame, array $arguments): bool
    {
        return isset($arguments[1]) && Convert::toBool($arguments[1]->evaluate($frame), $arguments[1]->domain(), $frame->context) === true;
    }

    /**
     * Quotes a value an error refuses: its text in UTF-8, binary bytes escaped, cut to 128 bytes with each byte of a broken last character as `?`.
     */
    public function quoted(string $text, Domain $domain): string
    {
        $shown = Convert::shown($text, $domain->kind === Kind::String ? $domain->collation->charset : null);
        if (strlen($shown) <= 128) {
            return $shown;
        }
        $cut = substr($shown, 0, 128);
        $whole = $cut;
        while (!mb_check_encoding($whole, 'UTF-8')) {
            $whole = substr($whole, 0, -1);
        }

        return $whole . str_repeat('?', strlen($cut) - strlen($whole));
    }

    /**
     * UUID_TO_BIN(text[, swap]): the 16 bytes of a UUID, the time-high part first when swapped.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws \MySqlMemory\Error\SqlError When the text is not a UUID
     */
    public function uuidToBin(Frame $frame, array $arguments, Domain $result): ?string
    {
        $text = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        if ($text === null) {
            return null;
        }
        $bytes = $this->bytes($text) ?? throw DataError::WrongValueForType->error('string', $this->quoted($text, $arguments[0]->domain()), 'uuid_to_bin');

        return $this->swapped($frame, $arguments) ? substr($bytes, 6, 2) . substr($bytes, 4, 2) . substr($bytes, 0, 4) . substr($bytes, 8) : $bytes;
    }

    /**
     * BIN_TO_UUID(bytes[, swap]): the string form of 16 bytes, read with the time-high part first when swapped.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws \MySqlMemory\Error\SqlError When the value is not 16 bytes
     */
    public function binToUuid(Frame $frame, array $arguments, Domain $result): ?string
    {
        $bytes = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        if ($bytes === null) {
            return null;
        }
        if (strlen($bytes) !== 16) {
            throw DataError::WrongValueForType->error('string', $this->quoted($bytes, $arguments[0]->domain()), 'bin_to_uuid');
        }
        if ($this->swapped($frame, $arguments)) {
            $bytes = substr($bytes, 4, 4) . substr($bytes, 2, 2) . substr($bytes, 0, 2) . substr($bytes, 8);
        }
        $hex = bin2hex($bytes);

        return (new Hashes())->written(substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20), $result);
    }
}
