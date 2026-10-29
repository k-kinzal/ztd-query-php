<?php

declare(strict_types=1);

namespace Fuzz\Target;

/**
 * Validates every section of a standard InnoDB monitor report.
 *
 * Physical allocations, background work, transaction identifiers and rates
 * belong to each process. User-row totals, queued queries and read views remain
 * comparison values. Unknown lines or sections fail closed, including foreign
 * key and deadlock reports that need a separate semantic comparison.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-standard-monitor.html.
 */
final class InnoDbStatus
{
    /**
     * Ordered sections and their complete content domains.
     * {N} is an unsigned integer; {F} is a nonnegative rate. Optional worker and hash
     * allocation records may be absent in a process without those allocations.
     */
    private const SECTIONS = [
        'BACKGROUND THREAD' => 'srv_master_thread loops: {N} srv_active, {N} srv_shutdown, {N} srv_idle\nsrv_master_thread log flush and writes: {N}\n',
        'SEMAPHORES' => '(?:OS WAIT ARRAY INFO: reservation count {N}\n)?(?:OS WAIT ARRAY INFO: signal count {N}\n)?'
            . '(?:Mutex spin waits {N}, rounds {N}, OS waits {N}\n)?RW-shared spins {N}, rounds {N}, OS waits {N}\nRW-excl spins {N}, rounds {N}, OS waits {N}\n'
            . '(?:RW-sx spins {N}, rounds {N}, OS waits {N}\n)?Spin rounds per wait: (?:{F} mutex, )?{F} RW-shared, {F} RW-excl(?:, {F} RW-sx)?\n',
        'TRANSACTIONS' => "Trx id counter {N}\nPurge done for trx's n:o < {N} undo n:o < {N} state: (?:running but idle|running|stopped)\n"
            . 'History list length {N}\nLIST OF TRANSACTIONS FOR EACH SESSION:\n'
            . '(?:---TRANSACTION {N}, (?:not started|ACTIVE {N} sec)\n{N} lock struct\(s\), heap size {N}, {N} row lock\(s\)\n)*',
        'FILE I/O' => '(?:I/O thread {N} state: waiting for completed aio requests \((?:insert buffer|log|read|write) thread\)\n)*'
            . '(?:Pending normal aio reads: \[{N}(?:, {N})*\] , aio writes: \[{N}(?:, {N})*\] ,\n ibuf aio reads:\n)?'
            . 'Pending flushes \(fsync\) log: {N}; buffer pool: {N}\n{N} OS file reads, {N} OS file writes, {N} OS fsyncs\n'
            . '{F} reads/s, {N} avg bytes/read, {F} writes/s, {F} fsyncs/s\n',
        'INSERT BUFFER AND ADAPTIVE HASH INDEX' => 'Ibuf: size {N}, free list len {N}, seg size {N}, {N} merges\n'
            . 'merged operations:\n insert {N}, delete mark {N}, delete {N}\ndiscarded operations:\n insert {N}, delete mark {N}, delete {N}\n'
            . '(?:Hash table size {N}, node heap has {N} buffer\(s\)\n)*{F} hash searches/s, {F} non-hash searches/s\n',
        'LOG' => 'Log sequence number +{N}\n(?:Log buffer assigned up to +{N}\nLog buffer completed up to +{N}\nLog written up to +{N}\n)?'
            . 'Log flushed up to +{N}\n(?:Added dirty pages up to +{N}\n)?Pages flushed up to +{N}\nLast checkpoint at +{N}\n'
            . "(?:Log minimum file id is +{N}\nLog maximum file id is +{N}\n)?{N} log i/o's done, {F} log i/o's/second\n",
        'BUFFER POOL AND MEMORY' => 'Total large memory allocated {N}\nDictionary memory allocated {N}\nBuffer pool size +{N}\nFree buffers +{N}\n'
            . 'Database pages +{N}\nOld database pages +{N}\nModified db pages +{N}\nPending reads +{N}\n'
            . 'Pending writes: LRU {N}, flush list {N}, single page {N}\nPages made young {N}, not young {N}\n{F} youngs/s, {F} non-youngs/s\n'
            . 'Pages read {N}, created {N}, written {N}\n{F} reads/s, {F} creates/s, {F} writes/s\n'
            . '(?:No buffer pool page gets since the last printout|Buffer pool hit rate {N} / 1000, young-making rate {N} / 1000 not {N} / 1000)\n'
            . 'Pages read ahead {F}/s, evicted without access {F}/s, Random read ahead {F}/s\nLRU len: {N}, unzip_LRU len: {N}\nI/O sum\[{N}\]:cur\[{N}\], unzip sum\[{N}\]:cur\[{N}\]\n',
        'ROW OPERATIONS' => '{N} queries inside InnoDB, (?<queued>{N}) queries in queue\n(?<views>{N}) read views open inside InnoDB\n'
            . 'Process ID={N}, Main thread ID=(?:0x[0-9a-fA-F]+|{N}) , state=(?:sleeping|enforcing dict cache limit|purging|doing background drop tables|waiting for server activity)\n'
            . 'Number of rows inserted (?<inserted>{N}), updated (?<updated>{N}), deleted (?<deleted>{N}), read (?<read>{N})\n'
            . '{F} inserts/s, {F} updates/s, {F} deletes/s, {F} reads/s\n'
            . '(?:Number of system rows inserted {N}, updated {N}, deleted {N}, read {N}\n{F} inserts/s, {F} updates/s, {F} deletes/s, {F} reads/s\n)?',
    ];

    /**
     * Returns SQL-related counters only after validating the entire report.
     *
     * @return array<string, string>|null Null denotes an invalid or unhandled report
     */
    public static function logical(string $report): ?array
    {
        $end = "----------------------------\nEND OF INNODB MONITOR OUTPUT\n============================\n";
        if (!str_ends_with($report, $end)) {
            return null;
        }
        $parts = preg_split('/^-+\n([A-Z][A-Z \/]+)\n-+\n/m', substr($report, 0, -strlen($end)), flags: PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false || count($parts) !== 1 + 2 * count(self::SECTIONS)) {
            return null;
        }
        $header = '\n=+\n(?:[0-9]{4}-[0-9]{2}-[0-9]{2}|[0-9]{6}) [0-9]{2}:[0-9]{2}:[0-9]{2} (?:0x[0-9a-fA-F]+|{N}) INNODB MONITOR OUTPUT\n=+\nPer second averages calculated from the last {N} seconds\n';
        if (!self::matches($header, $parts[0])) {
            return null;
        }
        $offset = 1;
        $logical = [];
        foreach (self::SECTIONS as $name => $pattern) {
            if ($parts[$offset] !== $name || !self::matches($pattern, $parts[$offset + 1])) {
                return null;
            }
            if ($name === 'ROW OPERATIONS') {
                preg_match(self::pattern($pattern), $parts[$offset + 1], $values);
                foreach (['queued', 'views', 'inserted', 'updated', 'deleted', 'read'] as $field) {
                    $logical[$field] = $values[$field];
                }
            }
            $offset += 2;
        }
        return $logical;
    }

    /**
     * Requires a complete section match, rejecting negative or overwide counters.
     */
    public static function matches(string $pattern, string $text): bool
    {
        if (preg_match(self::pattern($pattern), $text) !== 1) {
            return false;
        }
        preg_match_all('/(?<![0-9])[0-9]{20}(?![0-9])/', $text, $integers);
        foreach ($integers[0] as $integer) {
            if (strcmp($integer, '18446744073709551615') > 0) {
                return false;
            }
        }
        return true;
    }

    /**
     * Expands numeric domains without changing literal field names.
     */
    public static function pattern(string $pattern): string
    {
        return '~\A' . str_replace(['{N}', '{F}'], ['(?:0|[1-9][0-9]{0,19})', '(?:0|[1-9][0-9]{0,19})\.[0-9]+'], $pattern) . '\z~';
    }
}
