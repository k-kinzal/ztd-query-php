"""Compare PostgreSQL truncation with an independent model of ordered table effects."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_join import main, probe
from run_server_postgres_values import DIAGNOSTIC, TYPES, specifications as profiles
from sqlite_domain import RenderFault

LABEL = "postgres-truncate"
OBSERVER = "postgres_truncate_observer.php"
CONTRACT_NAME = "postgres-truncate-v1.json"
CONTRACT = "3c9ef972d86bd023c234796235d58e598ddee4e54e80db2b76b9832aa58f663e"
OBSERVATION_CONTRACT = "030a9e2ea42a3979cdc9d83cbd2ac922d477fb2f4b947b5e98d433688e61d150"
COMPLETE_SEEDS = True
SCHEMA, DECOY = "public", "ir_tr_decoy"
A, B, CHILD, REF, LEAF, ABSENT = ("ir_tr_" + name for name in ("a", "b", "child", "ref", "leaf", "absent"))
AXES = ("fixture", "targets", "restart", "cascade")
SEMANTIC = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\SemanticException"
HEADING = [["id", *TYPES["integer"], True], ["label", *TYPES["text"], False]]


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-truncate-contract-mismatch")
    require(digest(TOOL / "postgres-truncate-observation-v1.json") == OBSERVATION_CONTRACT,
            "postgres-truncate-observation-contract-mismatch")
    return json.loads(path.read_text()), profiles()[1]


def target(name=A, only=False, schema=SCHEMA):
    return [schema, name, only]


def case(tag, targets, restart=False, cascade=False, fixture="dependent"):
    return {"dialect": "postgres", "release": tag, "fixture": fixture,
            "truncateTable": {"targets": targets, "restart": restart, "cascade": cascade}}


def lists():
    return {
        "main": [target()],
        "independent-first": [target(B), target()],
        "with-dependents": [target(), target(REF), target(LEAF)],
        "dependents-first": [target(LEAF), target(REF), target()],
        "duplicate-only-first": [target(only=True), target()],
        "duplicate-all-first": [target(), target(only=True)],
    }


def finite(tag, fixture, form, restart, cascade):
    return case(tag, lists()[form], restart, cascade, fixture)


def finite_ids(_, spec, _tag):
    finite = spec["finite"]
    all_ids = set(product(*(finite[name] for name in AXES)))
    require(len(all_ids) == finite["identities"], "postgres-truncate-grid-size")
    return all_ids


def finite_id(_, reply, item):
    action = reply["truncateTable"]
    forms = [role for role, targets in lists().items() if targets == action["targets"]]
    require(len(forms) == 1, "postgres-truncate-unregistered-finite-list", action)
    return item["fixture"], forms[0], action["restart"], action["cascade"]


def analyzed_request(tag, sql, declarations=None, complete=True):
    if declarations is None:
        declarations = [f"CREATE TABLE {SCHEMA}.{table} (id integer)" for table in (A, B, CHILD, REF, LEAF)]
        declarations.append(f"CREATE TABLE {DECOY}.{A} (id integer)")
    return {"dialect": "postgres", "release": tag, "sql": sql, "declarations": declarations, "complete": complete}


def analyzed(tag):
    yield f"TRUNCATE {A}", case(tag, [target()], fixture="plain")
    yield f"TRUNCATE TABLE ONLY {SCHEMA}.{A} RESTART IDENTITY CASCADE", case(tag, [target(only=True)], True, True)
    yield f"TRUNCATE TABLE {A} * RESTART IDENTITY CASCADE", case(tag, [target()], True, True)
    yield f"TRUNCATE TABLE {A}, {REF}, {LEAF} CONTINUE IDENTITY RESTRICT", case(tag, lists()["with-dependents"])
    yield f"TRUNCATE {A} CASCADE", case(tag, [target()], cascade=True)
    yield f"TRUNCATE ONLY {A}, {A} CASCADE", case(tag, lists()["duplicate-only-first"], cascade=True)
    yield f"TRUNCATE {A}, ONLY {A} CASCADE", case(tag, lists()["duplicate-all-first"], cascade=True)
    yield f"TRUNCATE {DECOY}.{A} RESTART IDENTITY", case(tag, [target(schema=DECOY)], restart=True)


def negatives(tag):
    bad = "TRUNCATE requires a nonempty list of checked table targets."
    yield case(tag, []), DIAGNOSTIC, bad
    yield case(tag, ["ir_tr_a"]), DIAGNOSTIC, bad
    yield analyzed_request(tag, f"TRUNCATE {ABSENT}"), SEMANTIC, "does not exist"
    yield analyzed_request(tag, f"TRUNCATE {A}", [f"CREATE VIEW {SCHEMA}.{A} AS SELECT 1 AS id"]), SEMANTIC, "is not a table"
    yield case(tag, [target(A, schema="pg_temp_4")]), DIAGNOSTIC, "A persistent PostgreSQL relation requires an absolute schema."
    yield analyzed_request(tag, f"TRUNCATE {ABSENT}", complete=False), DIAGNOSTIC, "TRUNCATE requires a resolved persistent base table."


def seeded(tag, seed, count):
    rng = Random(seed)
    for position in range(count):
        count_targets = 1 + position % 5
        choices = [(SCHEMA, name) for name in (A, B, CHILD, REF, LEAF, ABSENT)] + [(DECOY, A)]
        targets = [[*rng.choice(choices), bool(rng.randrange(2))] for _ in range(count_targets)]
        fixture = "dependent" if rng.randrange(2) else "plain"
        yield case(tag, targets, bool(rng.randrange(2)), bool(rng.randrange(2)), fixture)


def requests(_, tag, spec):
    for index, dimensions in enumerate(product(*(spec["finite"][axis] for axis in AXES))):
        item = finite(tag, *dimensions)
        yield f"finite/{index}", item, item, None
    written = list(analyzed(tag))
    require(len(written) == len(spec["analysis"]), "postgres-truncate-analysis-count")
    for index, (sql, item) in enumerate(written):
        yield f"analyze/{index}", analyzed_request(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    bad = list(negatives(tag))
    require(len(bad) == len(spec["negative"]), "postgres-truncate-negative-count")
    for index, (item, exception, message) in enumerate(bad):
        yield f"negative/{index}", item, None, {"exception": exception, "message": message}


def coordinate(schema, table):
    return schema + "." + table


def table(fields, rows):
    return {"fields": fields, "rows": sorted(rows, key=json.dumps)}


def initial(fixture):
    a, b, decoy = (coordinate(*endpoint) for endpoint in [(SCHEMA, A), (SCHEMA, B), (DECOY, A)])
    tables = {a: table(HEADING, [[11, "dup"], [12, "dup"]]), b: table(HEADING, [[21, None], [22, "second"]]),
              decoy: table(HEADING, [[81, "decoy"]])}
    starts = {a + "_id_seq": 11, b + "_id_seq": 21, decoy + "_id_seq": 81}
    sequences = {a + "_id_seq": [12, True], b + "_id_seq": [22, True], decoy + "_id_seq": [81, True],
                 "public.ir_tr_unowned": [71, True]}
    if fixture == "dependent":
        tables[coordinate(SCHEMA, CHILD)] = table([*HEADING, ["note", *TYPES["text"], False]], [[99, "inherit", "child"]])
        for name, label, start, relation in ((REF, "pid", 31, 11), (LEAF, "rid", 41, 31)):
            qualified = coordinate(SCHEMA, name)
            tables[qualified] = table([HEADING[0], [label, *TYPES["integer"], False]], [[start, relation]])
            starts[qualified + "_id_seq"] = start
            sequences[qualified + "_id_seq"] = [start, True]
    return {"tables": tables, "sequences": sequences, "events": [], "marker": [0]}, starts


def expand(action, current, fixture):
    order = []
    for schema, table, only in action["targets"]:
        identity = coordinate(schema, table)
        if identity not in current:
            return [], ["db-error", "42P01"]
        if identity in order:
            continue
        order.append(identity)
        if fixture == "dependent" and schema == SCHEMA and table == A and not only:
            child = coordinate(SCHEMA, CHILD)
            if child not in order:
                order.append(child)
    if fixture == "dependent":
        edges = [(coordinate(SCHEMA, owner), coordinate(SCHEMA, dependent)) for owner, dependent in ((A, REF), (REF, LEAF))]
        for owner, dependent in edges:
            if owner in order and dependent not in order:
                if not action["cascade"]:
                    return [], ["db-error", "0A000"]
                order.append(dependent)
    return order, ["executed"]


def prediction(item):
    action = item["truncateTable"]
    original, starts = initial(item["fixture"])
    order, effect = expand(action, original["tables"], item["fixture"])
    data = dict(original["tables"])
    sequences = dict(original["sequences"])
    for relation in order:
        data[relation] = {**data[relation], "rows": []}
        sequence = relation + "_id_seq"
        if action["restart"] and sequence in starts:
            sequences[sequence] = [starts[sequence], False]
    events = [[*relation.split(".", 1), phase, "TRUNCATE"] for phase in ("BEFORE", "AFTER") for relation in order]
    after = {"tables": data, "sequences": sequences, "events": events, "marker": [0, 7]}
    return {"status": "postgres-truncate-effect", "namespace": [SCHEMA, SCHEMA, ["pg_catalog", SCHEMA]],
            "original": original, "effect": effect, "after": after, "active": True, "restored": original,
            "stableIdentities": {"after": True, "restored": True}}


def normalize(state):
    return {**state, "tables": {name: {"fields": rel["fields"], "rows": sorted(rel["rows"], key=json.dumps)}
                               for name, rel in state["tables"].items()}}


def identities(state):
    result = {name: table.get("oid") for name, table in state["tables"].items()}
    require(all(type(oid) is int and oid > 0 for oid in result.values()) and len(set(result.values())) == len(result),
            "postgres-truncate-missing-native-identity", result)
    return result


def observation(raw):
    # Keep the unprojected catalog observations in the replay ledger.
    return raw


def semantic_observation(raw):
    original = identities(raw["original"])
    stable = {phase: identities(raw[phase]) == original for phase in ("after", "restored")}
    return {**raw, **{phase: normalize(raw[phase]) for phase in ("original", "after", "restored")}, "stableIdentities": stable}


def check_value(item, response):
    require(response["status"] == "constructed" and response["profile"] == item["release"] + "/utf8/standard-conforming-strings/native",
            "postgres-truncate-positive-rejected", response)
    same(item["truncateTable"], response["truncateTable"])


def observer_request(item, sql):
    return {"sql": sql, "fixture": item["fixture"]}


def prepare(_, db):
    return {"initial": db.environment["initial"]}


def check_preparation(_, environment, preparation):
    same(environment["initial"], preparation["initial"])
    same(initial("dependent")[0], normalize(environment["initial"]))


def check_observation(item, actual, sql):
    wanted = prediction(item)
    witness = {"sql": sql, "expected": wanted, "observed": actual}
    require(actual["status"] == "postgres-truncate-effect", "postgres-truncate-observer-rejected", witness)
    try:
        same(wanted, semantic_observation(actual))
    except Failure as error:
        raise Failure(error.signal, witness) from error


def sample_forms(forms, reply, _, item):
    action = reply["truncateTable"]
    names = {(schema, name) for schema, name, _ in action["targets"]}
    for role, choice in [("length", len(action["targets"])), ("fixture", item["fixture"]), ("cascade", action["cascade"]),
                         ("restart", action["restart"]), ("repetition", len(names) != len(action["targets"]))]:
        forms[f"{role}/{choice}"] += 1
    for schema, name, only in action["targets"]:
        forms[f"schema/{schema}"] += 1
        forms[f"target/{name}"] += 1
        forms[f"only/{only}"] += 1


def required_forms(*_):
    return {*(f"length/{n}" for n in range(1, 6)), "fixture/plain", "fixture/dependent", f"schema/{SCHEMA}",
            f"schema/{DECOY}", *(f"target/{name}" for name in (A, B, CHILD, REF, LEAF, ABSENT)),
            *(f"{choice}/{value}" for choice in ("cascade", "restart", "repetition", "only") for value in (True, False))}


def catalog_control(item):
    predicted = prediction(item)
    identifiers = {name: index for index, name in enumerate(predicted["original"]["tables"], 100)}
    del predicted["stableIdentities"]
    for phase in ("original", "after", "restored"):
        state = predicted[phase]
        predicted[phase] = {
            **state,
            "tables": {name: {**relation, "oid": identifiers[name]} for name, relation in state["tables"].items()},
        }
    return predicted


def controls():
    item = case("pg-17.2", lists()["duplicate-only-first"], cascade=True)
    correct = catalog_control(item)
    check_observation(item, correct, "correct first occurrence and retained table identity")
    changed_identity = catalog_control(item)
    changed_identity["after"]["tables"][coordinate(SCHEMA, A)]["oid"] += 1000
    missing_identity = catalog_control(item)
    del missing_identity["after"]["tables"][coordinate(SCHEMA, A)]["oid"]
    alternative = catalog_control(case("pg-17.2", lists()["duplicate-all-first"], cascade=True))
    results = {}
    for name, input_state, signal in [
        ("misclassified-repeated-target", alternative, "meaning-mismatch"),
        ("reassigned-table-identity", changed_identity, "meaning-mismatch"),
        ("missing-table-identity", missing_identity, "postgres-truncate-missing-native-identity"),
    ]:
        try:
            check_observation(item, input_state, name)
        except Failure as error:
            same(signal, error.signal)
            results[name] = {"input": item, **error.detail}
        else:
            raise Failure("postgres-truncate-comparator-missed-control", name)
    return results


def boundary(item, response):
    if rejects(response, item["boundary"]):
        return
    require(response["status"] == "constructed", "postgres-truncate-mutant-response", response)
    raise Failure("postgres-truncate-invalid-published", {"sql": response["sql"], "observed": response, "expected": item["boundary"]})


def fault_probe(item, response, db):
    if "boundary" in item:
        boundary(item, response)
    else:
        probe(item, response, db, sys.modules[__name__])


def replay_fault(rule, record):
    witness = record["detail"]
    try:
        if "boundary" in rule.item:
            boundary(rule.item, record["healthyResponse"])
            boundary(rule.item, record["mutantResponse"])
        else:
            check_value(rule.item, record["healthyResponse"])
            check_value(rule.item, record["mutantResponse"])
            same(prediction(rule.item), witness["expected"])
            check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("postgres-truncate-missed-fault", rule.name)


def faults(_, tag):
    root = "sql-semantics-postgres/src/Ir/Schema/Truncate"
    table, target_path = f"{root}/TruncateTable.php", f"{root}/TruncateTarget.php"
    invalid, exception, message = list(negatives(tag))[0]
    return [
        RenderFault("truncate-redirect-to-decoy", target_path, "$this->table->quoted()", "'\"ir_tr_decoy\".\"ir_tr_a\"'",
                    case(tag, [target()], fixture="plain"), "meaning-mismatch"),
        RenderFault("truncate-erase-only", target_path, "($this->only ? 'ONLY ' : '')", "''",
                    case(tag, lists()["duplicate-only-first"], cascade=True), "meaning-mismatch"),
        RenderFault("truncate-erase-sequence-reset", table, "($this->restartIdentity ? ' RESTART IDENTITY' : ' CONTINUE IDENTITY')",
                    "' CONTINUE IDENTITY'", case(tag, [target()], restart=True, fixture="plain"), "meaning-mismatch"),
        RenderFault("truncate-erase-cascade", table, "($this->cascade ? ' CASCADE' : ' RESTRICT')", "' RESTRICT'",
                    case(tag, [target()], cascade=True), "meaning-mismatch"),
        RenderFault("truncate-alter-trigger-order", table, "implode(', ', $targets)", "implode(', ', array_reverse($targets))",
                    case(tag, lists()["dependents-first"]), "meaning-mismatch"),
        RenderFault("truncate-admit-empty-list", table, "'TRUNCATE requires a nonempty list of checked table targets.', 1",
                    "'TRUNCATE requires a nonempty list of checked table targets.', 0",
                    {"boundary": {"exception": exception, "message": message}}, "postgres-truncate-invalid-published", invalid),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
