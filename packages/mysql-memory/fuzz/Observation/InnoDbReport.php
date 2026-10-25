<?php

declare(strict_types=1);

namespace Fuzz\Observation;

/**
 * Checks monitor field names and numeric domains; only physical quantities vary.
 * Unknown lines retain the raw observation, so adding an unhandled report does not silently pass.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-standard-monitor.html.
 */
final class InnoDbReport
{
    /** @var array<string, list<string>> The fixed fields of each standard monitor section. */
    private const FIELDS = [
        'BACKGROUND THREAD' => ['srv_master_thread loops: {u} srv_active, {u} srv_shutdown, {u} srv_idle', 'srv_master_thread log flush and writes: {u}'],
        'SEMAPHORES' => ['OS WAIT ARRAY INFO: reservation count {u}', 'OS WAIT ARRAY INFO: signal count {u}', 'RW-shared spins {u}, rounds {u}, OS waits {u}', 'RW-excl spins {u}, rounds {u}, OS waits {u}', 'RW-sx spins {u}, rounds {u}, OS waits {u}', 'Spin rounds per wait: {f} RW-shared, {f} RW-excl', 'Spin rounds per wait: {f} RW-shared, {f} RW-excl, {f} RW-sx'],
        'TRANSACTIONS' => ['Trx id counter {u}', "Purge done for trx's n:o < {u} undo n:o < {u} state: {state}", 'History list length {u}', 'LIST OF TRANSACTIONS FOR EACH SESSION:'],
        'FILE I/O' => ['Pending flushes (fsync) log: {u}; buffer pool: {u}', '{u} OS file reads, {u} OS file writes, {u} OS fsyncs', '{f} reads/s, {u} avg bytes/read, {f} writes/s, {f} fsyncs/s'],
        'INSERT BUFFER AND ADAPTIVE HASH INDEX' => ['Ibuf: size {u}, free list len {u}, seg size {u}, {u} merges', 'merged operations:', 'insert {u}, delete mark {u}, delete {u}', 'discarded operations:', '{f} hash searches/s, {f} non-hash searches/s'],
        'LOG' => ['Log sequence number {u}', 'Log buffer assigned up to {u}', 'Log buffer completed up to {u}', 'Log written up to {u}', 'Log flushed up to {u}', 'Added dirty pages up to {u}', 'Pages flushed up to {u}', 'Last checkpoint at {u}', 'Log minimum file id is {u}', 'Log maximum file id is {u}', "{u} log i/o's done, {f} log i/o's/second"],
        'BUFFER POOL AND MEMORY' => ['Total large memory allocated {u}', 'Dictionary memory allocated {u}', 'Buffer pool size {u}', 'Free buffers {u}', 'Database pages {u}', 'Old database pages {u}', 'Modified db pages {u}', 'Pending reads {u}', 'Pending writes: LRU {u}, flush list {u}, single page {u}', 'Pages made young {u}, not young {u}', '{f} youngs/s, {f} non-youngs/s', 'Pages read {u}, created {u}, written {u}', '{f} reads/s, {f} creates/s, {f} writes/s', 'No buffer pool page gets since the last printout', 'Buffer pool hit rate {u} / 1000, young-making rate {u} / 1000 not {u} / 1000', 'Pages read ahead {f}/s, evicted without access {f}/s, Random read ahead {f}/s', 'LRU len: {u}, unzip_LRU len: {u}', 'I/O sum[{u}]:cur[{u}], unzip sum[{u}]:cur[{u}]'],
        'ROW OPERATIONS' => ['{u} queries inside InnoDB, {u} queries in queue', '{u} read views open inside InnoDB', 'Process ID={u}, Main thread ID={u} , state={state}', 'Number of rows inserted {u}, updated {u}, deleted {u}, read {u}', '{f} inserts/s, {f} updates/s, {f} deletes/s, {f} reads/s', 'Number of system rows inserted {u}, updated {u}, deleted {u}, read {u}'],
    ];

    /**
     * Returns the validated field structure, retaining missing fields as comparison differences.
     */
    public function comparable(string $text): ?string
    {
        if (preg_match('/\A\n=+\n(?:[0-9]{6}|[0-9]{4}-[0-9]{2}-[0-9]{2}) [ 0-2][0-9]:[0-5][0-9]:[0-5][0-9] (?:0x)?[0-9a-f]+ INNODB MONITOR OUTPUT\n=+\nPer second averages calculated from the last [0-9]+ seconds\n(.*)\n-+\nEND OF INNODB MONITOR OUTPUT\n=+\n\z/s', $text, $report) !== 1) {
            return null;
        }
        $parts = preg_split('/^-+\n([A-Z][A-Z \/]+)\n-+\n/m', $report[1], -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false || array_shift($parts) !== '' || count($parts) !== count(self::FIELDS) * 2) {
            return null;
        }
        $structure = [];
        foreach (array_keys(self::FIELDS) as $index => $section) {
            if ($parts[$index * 2] !== $section) {
                return null;
            }
            $fields = [];
            foreach (explode("\n", trim($parts[$index * 2 + 1])) as $line) {
                $line = preg_replace('/\h+/', ' ', trim($line)) ?? '';
                $field = $this->field($section, $line);
                if ($field === null) {
                    return null;
                }
                if ($field !== '') {
                    $fields[$field] = true;
                }
            }
            ksort($fields);
            $structure[] = $section . "\n" . implode("\n", array_keys($fields));
        }
        return implode("\n", $structure);
    }

    /**
     * Checks every line against a named field or a physical allocation record.
     */
    public function field(string $section, string $line): ?string
    {
        foreach (self::FIELDS[$section] ?? [] as $template) {
            $pattern = str_replace(['\{u\}', '\{f\}', '\{state\}'], ['[0-9]{1,20}', '[0-9]+\.[0-9]+', '(?:running|running but idle|stopped|sleeping|waiting for server activity)'], preg_quote($template, '/'));
            if (preg_match('/\A' . $pattern . '\z/', $line) === 1) {
                return str_starts_with($template, 'No buffer pool page gets') || str_starts_with($template, 'Buffer pool hit rate') ? '<buffer-pool-hit-rate>' : $template;
            }
        }
        $physical = match ($section) {
            'TRANSACTIONS' => '/\A(?:---TRANSACTION [0-9]+, not started|[0-9]+ lock struct\(s\), heap size [0-9]+, [0-9]+ row lock\(s\))\z/',
            'FILE I/O' => '/\A(?:I\/O thread [0-9]+ state: waiting for completed aio requests \((?:insert buffer|log|read|write) thread\)|Pending normal aio reads: \[[0-9, ]*\] , aio writes: \[[0-9, ]*\] ,|ibuf aio reads:)\z/',
            'INSERT BUFFER AND ADAPTIVE HASH INDEX' => '/\AHash table size [0-9]+, node heap has [0-9]+ buffer\(s\)\z/',
            default => null,
        };
        return $physical !== null && preg_match($physical, $line) === 1 ? '' : null;
    }
}
