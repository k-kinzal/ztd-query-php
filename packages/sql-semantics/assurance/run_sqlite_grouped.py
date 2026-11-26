"""Compare SQLite grouped counts and representative rows under fixed builtin collations."""

import json
import time
from collections import Counter

from run import TOOL, Failure, Ledger, Worker, digest, require, write_json
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain
from sqlite_engine import Database, ObserverAuthorizationError, typed
from sqlite_grouped_cases import COLLATIONS, FIXTURE, SCHEMA, analyzed, boundaries, case, compare, count, expected
from sqlite_grouped_cases import finite_cases, finite_id, finite_ids, negatives, seeded, slot, source
from sqlite_result_cases import integer

CONTRACT_NAME = "sqlite-grouped-v2.json"
CONTRACT = "2ef368050fa99f784e75c811988bc5ac3951300fd48d575193bc55a8faf74662"
POLICY = "c3ec0f3c57b38b838a34a357d4cb83e10ada3212a6308e2fa3f4bd153193024b"
FIELDS = ("operation", "source", "keys", "columns", "where")
NEGATIVE = r"SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction"


class GroupedDatabase(Database):
    policy_digest = POLICY

    def __init__(self, library):
        super().__init__(library)
        path = TOOL / "sqlite-grouped-observer-v1.json"
        require(digest(path) == POLICY, "sqlite-grouped-observer-contract-mismatch")
        specification = json.loads(path.read_text())
        self.allowed_actions = frozenset(specification["allowedActions"].values())
        self.function = specification["allowedFunction"]

    def authorize(self, context, action, first, second, schema, origin):
        if action == self.function["action"] and second == self.function["name"].encode() and origin is None:
            return 0
        return super().authorize(context, action, first, second, schema, origin)


def ask(db, sql):
    try:
        return db.observe(sql, {})
    except ObserverAuthorizationError as error:
        return {"denied": error.action, "arguments": error.arguments}


def controls(db):
    cases = {name: ask(db, sql) for name, sql in (
        ("count", "SELECT count(*) AS n"), ("sum", "SELECT sum(1)"),
        ("operation", "DELETE FROM ir_group_bag"), ("recovery", "SELECT count(*) AS n"),
    )}
    for name in ("count", "recovery"):
        require(cases[name]["rows"] == [[typed(1)]] and cases[name]["readOnly"], "sqlite-grouped-observer-positive", cases[name])
    require(cases["sum"] == {"denied": 31, "arguments": [None, "sum", None, None]},
            "sqlite-grouped-observer-function", cases["sum"])
    require(cases["operation"].get("denied") == 9, "sqlite-grouped-observer-write", cases["operation"])
    item = case(None, [], [[count(), "n"]])
    compare(item, cases["count"], "known valid count")
    mismatches = {
        "incorrect-count": {**cases["count"], "rows": [[typed(2)]]},
        "incorrect-type": {**cases["count"], "rows": [[typed(1.0)]]},
        "missing-output": {**cases["count"], "rows": []},
        "incorrect-output-label": {**cases["count"], "names": ["changed"]},
        "added-row": {**cases["count"], "rows": [[typed(1)], [typed(1)]]},
    }
    observed = {}
    for name, response in mismatches.items():
        try:
            compare(item, response, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-grouped-comparator-wrong-signal", name)
            observed[name] = error.signal
        else:
            raise Failure("sqlite-grouped-comparator-accepted", name)
    representative = case(source("bag"), [[slot(0), "NOCASE"]], [[slot(0), "a"], [slot(0), "b"], [count(), "n"]])
    metadata, choices = expected(representative)
    healthy = {**metadata, "rows": [group[0] for group in choices]}
    compare(representative, healthy, "known consistent group representatives")
    index = next(index for index, group in enumerate(choices) if len(group) > 1 and group[0][0] != group[1][0])
    mixed = [list(row) for row in healthy["rows"]]
    mixed[index][0] = choices[index][1][0]
    bad = {**metadata, "rows": mixed}
    try:
        compare(representative, bad, "mixed values from separate representatives")
    except Failure as error:
        require(error.signal == "meaning-mismatch", "sqlite-grouped-representative-control-wrong-signal", error.signal)
    else:
        raise Failure("sqlite-grouped-comparator-accepted-mixed-row", bad)
    return {"observer": cases, "comparator": {"input": item, "mismatches": mismatches, "detections": observed},
            "representative": {"input": representative, "healthy": healthy, "mixed": bad, "detected": "meaning-mismatch"}}


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed", "sqlite-grouped-positive-rejected", reply)
    require(reply["profile"].endswith("/grouped-builtin"), "sqlite-grouped-profile", reply)
    require(all(item[field] == reply[field] for field in FIELDS), "sqlite-grouped-shape-mismatch", {"expected": item, "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    observations = {"observation": db.observe(reply["sql"], {}, preserve_order=True)}
    if original is not None:
        observations["original"] = db.observe(original, {}, preserve_order=True)
    if ledger is not None:
        ledger.record(key, "observed", **observations)
    for name, value in observations.items():
        compare(item, value, original if name == "original" else reply["sql"])
    if ledger is not None:
        ledger.record(key, "compared")


def sample(forms, structure):
    keys = structure["keys"]
    forms["relation/" + ("unit" if structure["source"] is None else structure["source"][1])] += 1
    forms["keys/" + ("global" if not keys else "single" if len(keys) == 1 else "multiple")] += 1
    fields = [column[1] for column, _ in keys]
    if len(set(fields)) != len(fields):
        forms["keys/repeated"] += 1
    for _, collation in keys:
        forms["collation/" + collation] += 1
    for expression, _ in structure["columns"]:
        if expression[0] == "column":
            forms["output/key"] += 1
        else:
            forms["output/" + ("rows" if expression[1] is None else expression[2] or "field")] += 1
    where = structure["where"]
    forms["filter/" + ("absent" if where is None else "null" if where[0] == "null" else "zero" if where[1] == "0" else "present")] += 1


def requests(specification):
    for index, item in enumerate(finite_cases()):
        yield f"finite/{index}", item
    for seed in specification["exploration"]["seeds"]:
        for index, item in enumerate(seeded(seed, specification["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item
    for index, item in enumerate(boundaries()):
        yield f"boundary/{index}", item


def execute(args, specification, db, worker_factory=Worker):
    settings = specification["exploration"]
    items = list(requests(specification))
    finite, forms = Counter(), Counter()
    comparisons = controls(db)
    current = None
    with (args.out / "events.jsonl").open("w") as stream, worker_factory(args, time.monotonic() + settings["timeoutSeconds"]) as worker:
        ledger = Ledger(stream)
        try:
            for offset in range(0, len(items), 16):
                batch = items[offset:offset + 16]
                current = {"batch": [item for _, item in batch]}
                for key, item in batch:
                    ledger.record(key, "attempted", input=item)
                replies = worker.request(current["batch"])
                require(isinstance(replies, list) and len(replies) == len(batch), "sqlite-grouped-batch-incomplete", replies)
                for (key, current), response in zip(batch, replies):
                    probe(current, response, db, ledger, key)
                    if key.startswith("finite/"):
                        finite[finite_id(response)] += 1
                    if key.startswith("seed/"):
                        sample(forms, response)
            require(finite.keys() == finite_ids() and set(finite.values()) == {1}, "sqlite-grouped-finite-incomplete", list(finite.items()))
            mandatory = {
                "relation/unit", "relation/ir_group_bag", "relation/ir_group_empty",
                "keys/global", "keys/single", "keys/multiple", "keys/repeated",
                "output/key", "output/rows", "output/field", "filter/absent", "filter/null", "filter/zero", "filter/present",
                *(f"{role}/{collation}" for role in ("output", "collation") for collation in COLLATIONS),
            }
            require(mandatory <= forms.keys(), "sqlite-grouped-structure-unobserved", sorted(mandatory - forms.keys()))
            for index, (sql, item) in enumerate(analyzed()):
                key = f"analysis/{index}"
                current = {"sql": sql, "declarations": list(SCHEMA)}
                ledger.record(key, "attempted", input=current, expected=item)
                probe(item, worker.request(current), db, ledger, key, original=sql)
            for index, (current, message) in enumerate(negatives()):
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                reply = worker.request(current)
                require(reply["status"] == "rejected" and reply["exception"] == NEGATIVE and message in reply["message"],
                        "sqlite-grouped-negative-boundary", reply)
                ledger.record(key, "rejected", response=reply)
            counts = complete_observations(ledger, settings)
        except Exception as error:
            signal = error.signal if isinstance(error, Failure) else "unexpected-error"
            detail = error.detail if isinstance(error, Failure) else None
            for label, events in ledger.events.items():
                if events[-1] not in {"compared", "rejected"}:
                    ledger.record(label, "timeout" if signal == "timeout" else "failed", signal=signal, detail=detail)
            write_json(args.out / "counterexample.json", {"original": current, "failure": str(error), "signal": signal,
                                                        "detail": detail, "shrunk": None, "execution": dict(ledger.counts)})
            raise
        return {"counts": counts, "finiteIds": sorted(finite), "forms": dict(forms), "php": worker.environment, "controls": comparisons}


def check_faults(args, db, runner=check_render_faults):
    target = "sql-semantics-sqlite/src/Ir/Aggregation/GroupedSql.php"
    bag = source("bag")
    return runner(args, db, [
        RenderFault("wrong-count-slot", target, "$renderer->expression($value->argument, $query->source)", "$renderer->expression(new ColumnReference(0), $query->source)",
                    case(bag, [], [[count(slot(1), "NOCASE"), "n"]]), "meaning-mismatch"),
        RenderFault("wrong-group-collation", target, "$key->collation->value", "'BINARY'",
                    case(bag, [[slot(0), "NOCASE"]], [[count(), "n"]]), "meaning-mismatch"),
        RenderFault("omit-count-distinct", target, "'DISTINCT ' . $argument", "$argument",
                    case(bag, [], [[count(slot(1), "RTRIM"), "n"]]), "meaning-mismatch"),
        RenderFault("omit-group-filter", target, "if ($query->where !== null)", "if (false)",
                    case(bag, [], [[count(), "n"]], integer(0)), "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("grouped", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, GroupedDatabase)
