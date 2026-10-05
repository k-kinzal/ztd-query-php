#!/usr/bin/env python3
"""Run isolated, reproducible baseline/current candidate benchmarks."""
import argparse
import hashlib
import json
import os
import resource
import signal
from pathlib import Path
import subprocess
import time

parser = argparse.ArgumentParser()
parser.add_argument('--baseline-source', type=Path)
parser.add_argument('--output', type=Path, required=True)
parser.add_argument('--repetitions', type=int, default=3)
parser.add_argument('--cpu-seconds', type=int, default=5)
parser.add_argument('--timeout', type=float, default=120.0)
parser.add_argument('--shapes', nargs='+', help='Only run these fixture shapes.')
args = parser.parse_args()
root = Path(__file__).resolve().parent.parent
sources = sorted((root / 'src').rglob('*.php'))
implementation_hash = hashlib.sha256(b''.join(p.relative_to(root).as_posix().encode() + p.read_bytes() for p in sources)).hexdigest()
scenarios = [(shape, count) for shape in ('literal', 'local', 'return') for count in (0, 8, 16, 20, 100)]
scenarios += [('array', count) for count in (128, 1024, 4096, 8192)]
scenarios += [(shape, count) for shape in ('receiver', 'shared', 'tuple', 'loop') for count in (0, 20)]
scenarios += [('product', count) for count in (4, 8, 20)]
scenarios += [('receiver-choice', 2), ('callers', 2)]
if args.shapes:
    scenarios = [(shape, count) for shape, count in scenarios if shape in args.shapes]
versions = ['current'] if args.baseline_source is None else ['baseline', 'current']
args.output.parent.mkdir(parents=True, exist_ok=True)
with args.output.open('w') as output:
    for shape, count in scenarios:
        for repetition in range(args.repetitions):
            for version in versions:
                env = dict(os.environ, XDEBUG_MODE='off')
                env.pop('DERIVER_BASELINE_SOURCE', None)
                if version == 'baseline':
                    env['DERIVER_BASELINE_SOURCE'] = str(args.baseline_source.resolve())
                started = time.monotonic()
                record = {'version': version, 'shape': shape, 'size': count, 'repetition': repetition, 'cpu_limit_seconds': args.cpu_seconds, 'implementation_hash': implementation_hash if version == 'current' else 'fed0725f6220c06d8eebc5d349d61c5c2a588178'}
                try:
                    process = subprocess.run(['php', '-d', 'memory_limit=1G', str(root / 'bench/Contract.php'), str(count), shape], cwd=root, env=env, text=True, capture_output=True, timeout=args.timeout, preexec_fn=lambda: resource.setrlimit(resource.RLIMIT_CPU, (args.cpu_seconds, args.cpu_seconds)))
                    record.update(status='completed' if process.returncode == 0 else ('measurement-cpu-limit' if process.returncode in (-signal.SIGKILL, -signal.SIGXCPU) else 'error'), exit_code=process.returncode)
                    if process.returncode == 0:
                        record['measurement'] = json.loads(process.stdout)
                    else:
                        record['error'] = (process.stderr + process.stdout)[-2000:]
                except subprocess.TimeoutExpired:
                    record.update(status='measurement-timeout', timeout_seconds=args.timeout)
                record['process_seconds'] = time.monotonic() - started
                output.write(json.dumps(record, separators=(',', ':')) + '\n')
                output.flush()
                print(version, shape, count, repetition, record['status'], flush=True)
