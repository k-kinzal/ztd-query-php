"""Typed inputs and an independent SQLite transaction-stack transition model."""

import itertools
import random
import string

from sqlite_engine import typed

BEGIN = ("BEGIN DEFERRED", "BEGIN IMMEDIATE", "BEGIN EXCLUSIVE")
PLAIN = (*BEGIN, "COMMIT", "ROLLBACK")
NAMED = ("SAVEPOINT", "RELEASE SAVEPOINT", "ROLLBACK TO SAVEPOINT")
LABELS = ("S", "s", "é", "É", "")
PREFIXES = {
    "idle": [],
    "explicit transaction": [["BEGIN DEFERRED", None], ["write", 1]],
    "outer savepoint transaction": [["SAVEPOINT", "S"], ["write", 1]],
    "explicit transaction with duplicate and nested savepoints": [
        ["BEGIN DEFERRED", None], ["write", 1], ["SAVEPOINT", "s"], ["write", 2],
        ["SAVEPOINT", "S"], ["write", 3], ["SAVEPOINT", "é"], ["write", 4],
    ],
}
FOLD = str.maketrans(string.ascii_uppercase, string.ascii_lowercase)


def request(command, name=None, prefix=(), followups=(), **attributes):
    return {"operation": "transaction", "command": command, "name": name,
            "prefix": list(prefix), "followups": list(followups), **attributes}


def finite_cases():
    commands = [(command, None) for command in PLAIN] + list(itertools.product(NAMED, LABELS))
    for state, (command, name) in itertools.product(PREFIXES, commands):
        probes = [["write", 9], ["ROLLBACK TO SAVEPOINT", "s"], ["RELEASE SAVEPOINT", "S"], ["ROLLBACK", None]]
        yield request(command, name, PREFIXES[state], probes, prefixId=state)


def make_steps(rng, size, names, start):
    steps = []
    for index in range(size):
        command = rng.choice(["write", "write", *PLAIN, *NAMED])
        operand = start + index if command == "write" else rng.choice(names) if command in NAMED else None
        steps.append([command, operand])
    return steps


def seeded(seed, settings):
    rng = random.Random(seed)
    for _ in range(settings["casesPerSeed"]):
        extra = "".join(rng.choice('sA!"λ É💾') for _ in range(rng.randint(1, 12)))
        names = (*LABELS, extra)
        prefix = make_steps(rng, rng.randint(0, settings["prefixSteps"]), names, 10)
        after = make_steps(rng, rng.randint(0, settings["suffixSteps"]), names, 30)
        command = rng.choice((*PLAIN, *NAMED))
        yield request(command, rng.choice(names) if command in NAMED else None, prefix, after)


def analyzed():
    named = PREFIXES["explicit transaction with duplicate and nested savepoints"]
    outer = PREFIXES["outer savepoint transaction"]
    long_name = "δ" * 140
    yield "BEGIN", request(BEGIN[0], followups=[["write", 5], ["COMMIT", None]])
    yield "BEGIN DEFERRED TRANSACTION ignored", request(BEGIN[0], followups=[["ROLLBACK", None]])
    yield "BEGIN IMMEDIATE TRANSACTION", request(BEGIN[1])
    yield "BEGIN EXCLUSIVE", request(BEGIN[2], prefix=outer)
    yield "END TRANSACTION ignored", request("COMMIT", prefix=named)
    yield "ROLLBACK TRANSACTION ignored", request("ROLLBACK", prefix=outer)
    yield "SAVEPOINT ''", request("SAVEPOINT", "", followups=[["RELEASE SAVEPOINT", ""]])
    yield 'RELEASE SAVEPOINT "A""b"', request("RELEASE SAVEPOINT", 'A"b', [["SAVEPOINT", 'a"B'], ["write", 6]])
    yield "ROLLBACK TRANSACTION ignored TO SAVEPOINT s", request("ROLLBACK TO SAVEPOINT", "s", named, [["RELEASE SAVEPOINT", "S"]])
    yield f'SAVEPOINT "{long_name}"', request("SAVEPOINT", long_name)


def negatives():
    for command, name in (("RELEASE SAVEPOINT", None), ("ROLLBACK TO SAVEPOINT", None),
                          ("COMMIT", "ignored"), (BEGIN[2], "")):
        yield request(command, name), "Only named transaction commands require a savepoint name."
    yield request("SAVEPOINT", "a\0b"), "A savepoint name must be UTF-8 without NUL."
    yield request("SAVEPOINT", nameHex="ff"), "A savepoint name must be UTF-8 without NUL."


def metadata(command, error):
    return {
        "names": [], "rows": [], "parameters": {}, "parameterCount": 0,
        "readOnly": command not in (BEGIN[1], BEGIN[2], "write"),
        "changes": int(command == "write"),
        "evaluationError": None if error is None else {"code": 1, "message": error},
    }


class State:
    def __init__(self):
        self.rows = (0,)
        self.before = None
        self.outer_savepoint = False
        self.stack = []

    def finish(self, rollback):
        if rollback:
            self.rows = self.before
        self.before = None
        self.stack.clear()
        self.outer_savepoint = False

    def checkpoint(self, command, name):
        wanted = name.translate(FOLD)
        for position in range(len(self.stack) - 1, -1, -1):
            label, rows = self.stack[position]
            if wanted != label:
                continue
            if command == "ROLLBACK TO SAVEPOINT":
                self.rows = rows
                del self.stack[position + 1:]
            else:
                del self.stack[position:]
                if not self.stack and self.outer_savepoint:
                    self.finish(False)
            return None
        return f"no such savepoint: {name}"

    def apply(self, command, operand):
        if command == "write":
            self.rows += (operand,)
        elif command in BEGIN:
            if self.before is not None:
                return "cannot start a transaction within a transaction"
            self.before = self.rows
        elif command in ("COMMIT", "ROLLBACK"):
            if self.before is None:
                return f"cannot {command.lower()} - no transaction is active"
            self.finish(command == "ROLLBACK")
        elif command == "SAVEPOINT":
            if self.before is None:
                self.before = self.rows
                self.outer_savepoint = True
            self.stack.append((operand.translate(FOLD), self.rows))
        elif command in NAMED:
            return self.checkpoint(command, operand)
        else:
            raise ValueError(command)
        return None

    def measure(self, command, operand):
        error = self.apply(command, operand)
        return {"statement": metadata(command, error), "autocommit": self.before is None,
                "rows": [[typed(value)] for value in self.rows]}


def expected(item):
    if item.get("lock"):
        command = item["command"]
        return {"statement": metadata(command, None), "reader": command != BEGIN[2], "writer": command == BEGIN[0],
                "rows": [[typed(0)], [typed(1)]] if command == BEGIN[0] else [[typed(0)]]}
    state = State()
    return {"prefix": [state.measure(*step) for step in item["prefix"]],
            "candidate": state.measure(item["command"], item["name"]),
            "followups": [state.measure(*step) for step in item["followups"]]}
