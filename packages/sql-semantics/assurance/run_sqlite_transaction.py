"""Compare complete SQLite transaction values with state transitions on the native engine."""

from collections import Counter
from pathlib import Path
import tempfile
import time

from run import Ledger, PROFILE, Worker, compare, require, write_json
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain
from sqlite_engine import Database
from sqlite_transaction_cases import BEGIN, NAMED, PLAIN, PREFIXES, analyzed, expected, finite_cases, negatives, request, seeded

CONTRACT = "52ba3cc00a6b7428a85d1ab7d9a55dd04cb9fcbecf0909ccc94a86e40c684afc"
FIXTURE = "CREATE TABLE transaction_probe (value INTEGER)"
ROWS = "SELECT value FROM transaction_probe ORDER BY rowid"
RESET = "DELETE FROM transaction_probe; INSERT INTO transaction_probe VALUES (0)"


def fixture_sql(command, operand):
    if command == "write":
        return f"INSERT INTO transaction_probe VALUES ({int(operand)})"
    if operand is None:
        return command
    quoted = operand.replace('"', '""')
    return f'{command} "{quoted}"'


def measure(db, sql):
    statement = db.observe(sql, {}, capture_evaluation_error=True)
    return {"statement": statement, "autocommit": bool(db.get_autocommit(db.db)),
            "rows": db.observe(ROWS, {}, preserve_order=True)["rows"]}


def reset(db):
    if not db.get_autocommit(db.db):
        db.execute("ROLLBACK")
    db.execute(RESET)


def probe_access(db, sql):
    try:
        db.execute(sql)
    except RuntimeError as error:
        require(str(error) == "SQLite 5: database is locked", "transaction-unexpected-lock-error", str(error))
        return False
    return True


def observe_locks(db, sql):
    with tempfile.TemporaryDirectory(prefix="semantics-ir-transaction-lock-") as scratch:
        database = str(Path(scratch) / "fixture.sqlite").encode()
        owner = Database(db.library, database)
        other = None
        try:
            owner.execute("PRAGMA journal_mode=DELETE")
            mode = owner.metadata("journal_mode")
            require(mode == [[["text", "delete"]]], "transaction-journal-mode-mismatch", mode)
            owner.execute(FIXTURE)
            owner.execute(RESET)
            other = Database(db.library, database)
            other.execute("PRAGMA busy_timeout=0")
            statement = owner.observe(sql, {}, capture_evaluation_error=True)
            reader = probe_access(other, ROWS)
            writer = probe_access(other, "INSERT INTO transaction_probe VALUES (1)")
            if not owner.get_autocommit(owner.db):
                owner.execute("ROLLBACK")
            return {"statement": statement, "reader": reader, "writer": writer,
                    "rows": owner.observe(ROWS, {}, preserve_order=True)["rows"]}
        finally:
            if other is not None:
                other.close(other.db)
            owner.close(owner.db)


def observe(db, item, sql):
    if item.get("lock"):
        return observe_locks(db, sql)
    reset(db)
    return {
        "prefix": [measure(db, fixture_sql(*step)) for step in item["prefix"]],
        "candidate": measure(db, sql),
        "followups": [measure(db, fixture_sql(*step)) for step in item["followups"]],
    }


def probe(item, response, db, ledger=None, key=None, original=None):
    require(response["status"] == "constructed", "transaction-positive-rejected", response)
    require(response["profile"] == PROFILE, "transaction-profile-mismatch", response)
    if ledger is not None:
        ledger.record(key, "constructed", response=response)
    for role in ("operation", "command", "name"):
        require(response[role] == item[role], "transaction-shape-mismatch", {"role": role, "response": response})
    observations = {"observation": observe(db, item, response["sql"])}
    if original is not None:
        observations["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **observations)
    for result in observations.values():
        compare(expected(item), result)
    if ledger is not None:
        ledger.record(key, "compared")


def required_identities(finite):
    return {
        (state, command, name)
        for state in finite["prefixes"]
        for command in finite["nameless"] + finite["named"]
        for name in finite["labels"] if command in finite["named"]
    } | {(state, command, None) for state in finite["prefixes"] for command in finite["nameless"]}


def execute(args, specification, db, worker_factory=Worker):
    settings = specification["exploration"]
    positives = [(f"finite/{index}", item) for index, item in enumerate(finite_cases())]
    positives.extend((f"seed/{seed}/{index}", item) for seed in settings["seeds"]
                     for index, item in enumerate(seeded(seed, settings)))
    positives.extend((f"lock/{index}", request(command, lock=True)) for index, command in enumerate(BEGIN))
    deadline = time.monotonic() + settings["timeoutSeconds"]
    finite_ids, commands = Counter(), Counter()
    explored = Counter()
    current = None
    with (args.out / "events.jsonl").open("w") as stream, worker_factory(args, deadline) as worker:
        ledger = Ledger(stream)
        try:
            for start in range(0, len(positives), 16):
                batch = positives[start:start + 16]
                current = {"batch": [item for _, item in batch]}
                for key, item in batch:
                    ledger.record(key, "attempted", input=item)
                replies = worker.request(current["batch"])
                require(type(replies) is list and len(replies) == len(batch), "transaction-batch-mismatch", replies)
                for (key, current), response in zip(batch, replies):
                    require(time.monotonic() < deadline, "timeout", key)
                    probe(current, response, db, ledger, key)
                    if key.startswith("finite/"):
                        finite_ids[(current["prefixId"], response["command"], response["name"])] += 1
                    if key.startswith("seed/"):
                        commands[response["command"]] += 1
                        explored[f"prefix/{len(current['prefix'])}"] += 1
                        explored[f"followups/{len(current['followups'])}"] += 1
                        if response["name"] is not None and '"' in response["name"]:
                            explored["quoted-name"] += 1
            require(set(finite_ids) == required_identities(specification["finite"]) and set(finite_ids.values()) == {1},
                    "transaction-finite-incomplete", list(finite_ids.items()))
            require(commands.keys() == set((*PLAIN, *NAMED)) and
                    {"prefix/0", f"prefix/{settings['prefixSteps']}", "followups/0",
                     f"followups/{settings['suffixSteps']}", "quoted-name"} <= explored.keys(),
                    "transaction-exploration-incomplete", {"commands": dict(commands), "structure": dict(explored)})
            for index, (sql, expected_item) in enumerate(analyzed()):
                current = {"sql": sql, "declarations": []}
                key = f"analysis/{index}"
                ledger.record(key, "attempted", input=current, expected=expected_item)
                probe(expected_item, worker.request(current), db, ledger, key, original=sql)
            for index, (current, message) in enumerate(negatives()):
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                response = worker.request(current)
                require(response["status"] == "rejected" and response["message"] == message,
                        "transaction-negative-boundary-mismatch", response)
                ledger.record(key, "rejected", response=response)
            require(time.monotonic() < deadline, "timeout", "transaction final accounting")
            counts = complete_observations(ledger, settings)
            for phase, setting in (("analysis", "analysisCases"), ("lock", "lockCases")):
                measured = {key for key, stages in ledger.events.items()
                            if key.startswith(phase + "/") and stages[-1] == "compared"}
                require(measured == {f"{phase}/{index}" for index in range(specification[setting])},
                        "transaction-phase-incomplete", phase)
        except Exception as error:
            write_json(args.out / "counterexample.json", {"original": current, "failure": str(error), "shrunk": None})
            raise
        finally:
            reset(db)
        return {"counts": counts, "finiteIds": sorted(finite_ids), "commands": dict(commands),
                "structure": dict(explored), "php": worker.environment}


def check_faults(args, db, runner=check_render_faults):
    prefix = PREFIXES["explicit transaction with duplicate and nested savepoints"]
    path = "sql-semantics-sqlite/src/Ir/Transaction/Transaction.php"
    command = "return $this->command->value ."
    return runner(args, db, [
        RenderFault("release-rollback-target", path, command,
                    "return ($this->command === TransactionCommand::RollbackTo ? TransactionCommand::Release : $this->command)->value .",
                    request("ROLLBACK TO SAVEPOINT", "s", prefix, [["ROLLBACK TO SAVEPOINT", "s"]]), "meaning-mismatch"),
        RenderFault("render-other-name", path, "$this->name->quoted()", "(new SavepointName('other'))->quoted()",
                    request("RELEASE SAVEPOINT", "s", prefix), "meaning-mismatch"),
        RenderFault("exclusive-instead-of-immediate", path, command,
                    "return ($this->command === TransactionCommand::BeginImmediate ? TransactionCommand::BeginExclusive : $this->command)->value .",
                    request(BEGIN[1], lock=True), "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("Transaction", "sqlite-transaction-v1.json", CONTRACT, FIXTURE, execute, check_faults)
