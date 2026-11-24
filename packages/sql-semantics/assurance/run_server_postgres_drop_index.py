"""Compare PostgreSQL DROP INDEX with an independent binding and catalog transition model."""

from hashlib import sha256
import json
from random import Random
import sys

from evidence_transcript import canonical
from postgres_drop_index_cases import (
    PATH, analyzed, boundary, case, coordinate_key, coordinates, exclusion, expected, finite, fixture,
)
from run import Failure, TOOL, digest, require
from run_server import rejects, same
from run_server_drop_table import specifications as drop_contract
from run_server_join import main, probe
from sqlite_domain import RenderFault

LABEL = "postgres-drop-index"
OBSERVER = "postgres_drop_index_observer.py"
CONTRACT_NAME = "postgres-drop-index-v1.json"
CONTRACT = "d26bab1348dec92d608759e02d68f184ee28461aadbaf015e836f911dced5faa"
COMPLETE_SEEDS = True
STOCK_NAMES = {
    "160006": (333, "36e2dd30e83b007fc2642d6b399d25ff53532341220c83590052e68f290ea825"),
    "170002": (335, "f87d290baf1479b65c7da43d2538c2c459efe5887c068c9c8f1414460a1b6d1c"),
}
GRID = {
    # Unqualified, front, and later path outcomes. The fourth qualifier never exists.
    "empty": ("absent", "absent", "absent"),
    "front": ("bound", "bound", "absent"),
    "later": ("bound", "absent", "bound"),
    "both": ("bound", "bound", "bound"),
    "table-shadows": ("wrong", "wrong", "bound"),
    "owned-constraint": ("protected", "absent", "protected"),
    "foreign-dependent": ("bound", "bound", "absent"),
}


def specifications():
    contract = TOOL / CONTRACT_NAME
    require(digest(contract) == CONTRACT, "postgres-drop-index-contract-mismatch")
    spec = json.loads(contract.read_text())
    require(spec["exploration"]["allowedExclusions"] == [], "postgres-drop-index-exclusions")
    spec["exploration"]["timeoutSecondsPerRelease"] = spec["exploration"]["timeoutSeconds"]
    spec["additionalPostgreSqlNegatives"] = [*spec["analysisNegative"], *range(244)]

    return spec, {"postgres": drop_contract()[1]["postgres"]}


def partition(spec):
    positives, negatives = set(), set()
    for layout, schema, guarded, behavior, concurrent, transaction in coordinates(spec):
        choices = dict(zip((None, "front", "later"), GRID[layout]))
        binding = choices.get(schema, "absent")
        enabled = (binding == "bound" or (binding == "absent" and guarded)) and not (concurrent and behavior == "CASCADE")
        identity = layout, schema or "", guarded, behavior, concurrent, transaction
        (positives if enabled else negatives).add(identity)
    require(len(positives) == 204 and len(negatives) == 244, "postgres-drop-index-finite-size")

    return positives, negatives


def finite_ids(_, spec, _tag):
    return partition(spec)[0]


def rejected_finite_ids(spec, _tag):
    return partition(spec)[1]


def finite_id(_, actual, item):
    spec, _ = specifications()
    return coordinate_key(spec, {**item, "dropIndex": actual["dropIndex"]})


def analysis_finite_id(engine, actual, item, _sql):
    return finite_id(engine, actual, item)


def direct_finite_id(engine, actual, item, _):
    return finite_id(engine, actual, item)


def observe_rejection(spec, item, observation, sql):
    require(exclusion(item) is not None and expected(item)["sqlState"] != "00000", "postgres-drop-index-rejection-domain")
    check_observation(item, observation, sql)

    return coordinate_key(spec, item)


def direct(tag, targets, guarded=False, cascade=False, mode="ordinary"):
    return {"dialect": "postgres", "release": tag, "dropIndex": {
        "targets": targets, "ifExists": guarded, "cascade": cascade, "mode": mode,
    }}


def negatives(tag):
    bound = ["bound", "front", "idx", "parent"]
    missing = ["missing", "front", "missing", False]
    yield direct(tag, []), boundary("ordered nonempty target list")
    yield direct(tag, [missing]), boundary("ordinary bound indexes or guarded absence")
    yield direct(tag, [["missing", None, "missing", True]], True), boundary("missing schema must name a namespace")
    yield direct(tag, [bound, bound], mode="concurrently"), boundary("CONCURRENTLY needs one target and RESTRICT")
    yield direct(tag, [bound], cascade=True, mode="concurrently"), boundary("CONCURRENTLY needs one target and RESTRICT")
    for layout, selectors in (("table-shadows", [(None, "idx")]), ("owned-constraint", [("later", "idx")]),
                              ("empty", [("front", "idx")]), ("front", [(None, "pg_class")])):
        path = ["pg_catalog", "front", "later", "public"] if selectors[0][1] == "pg_class" else PATH
        decoy = [("front", "pg_class")] if selectors[0][1] == "pg_class" else []
        item = case(tag, fixture(layout, decoy), selectors, path=path)
        yield analyzed(item), exclusion(item)
    item = case(tag, fixture("empty"), [("front", "idx")], guarded=True)
    yield {**analyzed(item), "complete": False}, boundary("cannot infer absence in an open index namespace")
    item = case(tag, fixture("later"), [(None, "idx")], guarded=True)
    yield {**analyzed(item), "complete": False}, boundary("cannot infer absence in an open index namespace")
    item = analyzed(case(tag, fixture("front"), [("front", "idx")]))
    item["declarations"].append("CREATE INDEX idx ON front.parent (id)")
    yield item, boundary("conflicting index declarations")


def generated(tag, seed, count):
    rng = Random(seed)
    for ordinal in range(count):
        temporary = bool(rng.getrandbits(1))
        permanent = ["front", "later"]
        scopes = permanent + (["pg_temp"] if temporary else [])
        names = ["odd index", 'quoted"index', "IDX", "écriture"]
        variants = [(schema, name) for schema in scopes for name in rng.sample(names, rng.randrange(1, 4))]
        layout = rng.choice(["front", "later", "both", "empty", "foreign-dependent"])
        state = fixture(layout, variants, temporary)
        first = [[schema, name] for schema, name, _, _, _, _ in state["indexes"]]
        selectors = [*first, *[[None, name] for _, name in first], ["absent_schema", "idx"], ["front", "missing"]]
        if not temporary:
            selectors.append(["pg_temp", "missing"])
        width = 1 + (ordinal % 4)
        concurrent = width == 1 and bool(rng.getrandbits(1))
        path = rng.sample(permanent, k=2) + ["public", "pg_catalog"]
        chosen = [rng.choice(selectors) for _ in range(width)]
        if ordinal % 9 == 0 and width >= 2:
            chosen[-1] = chosen[0]
        item = case(tag, state, chosen, guarded=True, cascade=not concurrent and bool(rng.getrandbits(1)),
                    concurrent=concurrent, transaction=bool(rng.getrandbits(1)), path=path)
        if not any(role[0] == "missing" for role in item["dropIndex"]["targets"]):
            item["dropIndex"]["ifExists"] = bool(rng.getrandbits(1))
        require(exclusion(item) is None, "postgres-drop-index-invalid-generator")

        yield item


def requests(_, tag, spec):
    for number, values in enumerate(coordinates(spec)):
        item = finite(tag, *values)
        failure = exclusion(item)
        if failure is None:
            yield f"finite/{number}", analyzed(item), item, None
            yield f"direct/{number}", item, item, None
        else:
            yield f"rejected-finite/{number}", analyzed(item), item, failure
    for seed in spec["exploration"]["seeds"]:
        for number, item in enumerate(generated(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{number}", item, item, None
    for number, (request, failure) in enumerate(negatives(tag)):
        yield f"negative/{number}", request, None, failure


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"] == item["release"] + "/utf8/standard-conforming-strings/native",
            "postgres-drop-index-positive-rejected", reply)
    require(reply["dropIndex"] == item["dropIndex"], "postgres-drop-index-binding",
            {"sql": reply["sql"], "expected": item["dropIndex"], "observed": reply["dropIndex"]})


def prepare(_, db):
    return {"stock": db.environment["stock"], "libpq": db.environment["libpq"]}


def check_preparation(_, environment, record):
    same(record, {"stock": environment["stock"], "libpq": environment["libpq"]})
    stock = record["stock"]
    digest = sha256(json.dumps(stock, ensure_ascii=False, separators=(",", ":")).encode()).hexdigest()
    require((len(stock), digest) == STOCK_NAMES[environment["database"][0]] and len(stock) == len({tuple(entry) for entry in stock}),
            "postgres-drop-index-stock-namespace", {"count": len(stock), "digest": digest})


def observer_request(item, sql):
    return {"sql": sql, "fixture": item["fixture"], "path": item["schemaPath"], "transaction": item["transaction"]}


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    prediction = expected(item)
    witness = {"sql": sql, "expected": prediction, "observed": actual}
    require(actual["status"] == "index-removal", "postgres-drop-index-observer-rejected", witness)
    require(canonical(prediction) == canonical(actual), "meaning-mismatch", witness)


def sample_forms(forms, reply, _, item):
    drop = reply["dropIndex"]
    roles = drop["targets"]
    forms[f"count/{len(roles)}"] += 1
    forms[f"guard/{drop['ifExists']}"] += 1
    forms[f"cascade/{drop['cascade']}"] += 1
    forms[f"mode/{drop['mode']}"] += 1
    forms[f"transaction/{item['transaction']}"] += 1
    for role, schema, name, detail in roles:
        forms[f"target/{role}"] += 1
        forms[f"scope/{schema}"] += 1
        forms[f"name/{'quoted' if not name.islower() or not name.isascii() or not name.isidentifier() else 'simple'}"] += 1
        if role == "missing":
            forms[f"missing-schema/{detail}"] += 1
    if len({tuple(role) for role in roles}) < len(roles):
        forms["target/repeated"] += 1


def required_forms(*_):
    return {
        *(f"count/{count}" for count in range(1, 5)),
        *(f"{axis}/{value}" for axis in ("guard", "cascade", "transaction", "missing-schema") for value in (True, False)),
        "mode/ordinary", "mode/concurrently", "target/bound", "target/missing", "target/repeated",
        "scope/front", "scope/later", "scope/pg_temp", "name/simple", "name/quoted",
    }


def controls():
    item = case("pg-17.2", fixture("foreign-dependent"), [(None, "idx")], cascade=True)
    native = expected(item)
    check_observation(item, native, "control")
    false_positive = dict(native, notices=[])
    try:
        check_observation(item, false_positive, "observed notice omitted")
    except Failure as failure:
        same("meaning-mismatch", failure.signal)
        return {"lost-cascade-notice": {"input": item, "witness": failure.detail}}
    raise Failure("postgres-drop-index-comparator-accepted")


def fault_probe(item, reply, db):
    if "boundary" not in item:
        probe(item, reply, db, sys.modules[__name__])

        return
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "postgres-drop-index-unexpected-mutant-crash", reply)
    raise Failure("postgres-drop-index-invalid-published",
                  {"sql": reply["sql"], "expected": item["boundary"], "observed": reply})


def replay_fault(rule, record):
    witness = record["detail"]
    try:
        if "boundary" in rule.item:
            fault_probe(rule.item, record["healthyResponse"], None)
            fault_probe(rule.item, record["mutantResponse"], None)
        else:
            check_value(rule.item, record["healthyResponse"])
            check_value(rule.item, record["mutantResponse"])
            same(expected(rule.item), witness["expected"])
            check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as failure:
        same(rule.signal, failure.signal)
        same(witness, failure.detail)
        return
    raise Failure("postgres-drop-index-fault-did-not-replay", rule.name)


def faults(_, tag):
    root = "sql-semantics-postgres/src/Ir/Schema/Index/"
    drop = root + "DropIndex.php"
    lowering = root + "DropIndexTargetLowering.php"
    binding = "sql-semantics-postgres/src/Model/Facade/Index/IndexBinding.php"
    selected = case(tag, fixture("both"), [("front", "idx")])
    two = case(tag, fixture("both"), [("front", "idx"), ("later", "idx")])
    missing = case(tag, fixture("empty"), [("absent_schema", "idx")], guarded=True)
    concurrent = case(tag, fixture("front"), [("front", "idx")], concurrent=True, transaction=True)
    dependent = case(tag, fixture("foreign-dependent"), [("front", "idx")], cascade=True)
    shadow = case(tag, fixture("table-shadows"), [(None, "idx")])
    invalid, reason = list(negatives(tag))[3]
    return [
        RenderFault("drop-index-select-other-schema", drop, "$names[] = $target->quoted();",
                    '''$names[] = '"later"."idx"';''', selected, "meaning-mismatch"),
        RenderFault("drop-index-remove-existence-guard", drop, "($this->ifExists ? 'IF EXISTS ' : '')", "''",
                    missing, "meaning-mismatch"),
        RenderFault("drop-index-drop-target-occurrence", drop,
                    "foreach ($this->targets as $target)", "foreach (array_slice($this->targets, 0, 1) as $target)",
                    two, "meaning-mismatch"),
        RenderFault("drop-index-lose-concurrency-route", drop,
                    "($this->mode === IndexDropMode::Concurrently ? 'CONCURRENTLY ' : '')", "''",
                    concurrent, "meaning-mismatch"),
        RenderFault("drop-index-substitute-restrict", drop, "($this->cascade ? ' CASCADE' : ' RESTRICT')", "' RESTRICT'",
                    dependent, "meaning-mismatch"),
        RenderFault("drop-index-change-absence-role", lowering, "$schema !== null && !$binding->known($context, $schema),",
                    "false,", missing, "postgres-drop-index-binding", analyzed(missing)),
        RenderFault("drop-index-admit-concurrent-list", drop, "count($targets) === 1 && !$cascade", "!$cascade",
                    {"boundary": reason}, "postgres-drop-index-invalid-published", invalid),
        RenderFault("drop-index-skip-wrong-kind", binding, "$context->declared($name, $schema) === [] && !StockIndexNames::contains",
                    "!StockIndexNames::contains", {"boundary": exclusion(shadow)}, "postgres-drop-index-invalid-published", analyzed(shadow)),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
