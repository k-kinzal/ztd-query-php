"""Reconcile native InnoDB support requirements with the resulting key catalog."""

from copy import deepcopy
import json
import sys

import mysql_index_fk_cases as laws
from run import TOOL, Failure, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_mysql_drop_index import DIAGNOSTIC, specifications as native_profiles
from sqlite_domain import RenderFault

LABEL = "mysql-index-foreign-keys"
OBSERVER = "mysql_index_fk_observer.php"
NAME = "mysql-index-foreign-keys-evidence-v1.json"
BASE = "9b17cccc8e5e73d603468857a7793cd6af4f03b0e44db20f55185ce2a182b8e0"
CONTRACT = "a561cae8497665c4ec88ff6701d3d6412acb994000e22844d0729765d611b0da"
PREFIX = "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/"
COMPARATOR_FAULTS = {
    "Remove one original ordered key term from the native observation.": "ignore-ordered-key",
    "Erase a persistent referential coordinate after execution.": "ignore-native-foreign-key",
}


def specifications():
    source, fixed = TOOL / "mysql-index-foreign-dependencies-v1.json", TOOL / NAME
    require(digest(source) == BASE and digest(fixed) == CONTRACT, "mysql-index-foreign-keys-contract")
    _, profiles = native_profiles()
    spec, base = json.loads(fixed.read_text()), json.loads(source.read_text())
    same(base["profiles"]["releases"], spec["releases"])
    same(spec["releases"], profiles["mysql"]["releases"])
    return spec, profiles


def finite_ids(_, spec, tag):
    coordinates = laws.finite_coords(spec["finite"])
    same(spec["finiteIdentities"], len(coordinates))
    provisioned = {point for point in coordinates if laws.coordinate(tag, point)["legal"]}
    same(spec["admitted"][tag], len(provisioned))
    return provisioned


def paired(source, coordinate, model):
    item = {"model": model}
    direct_source = "direct" if source == "finite" else "direct-seed"
    yield f"{source}/{coordinate}", laws.analyze(model), item, None
    yield f"{direct_source}/{coordinate}", laws.direct(model), item, None


def refused(source, name, model):
    for route, builder in (("analysis", laws.analyze), ("direct", laws.direct)):
        identity = f"{source}/{name}/{route}"
        yield f"negative/{identity}", builder(model), {"model": model, "rejectionId": identity}, {
            "exception": DIAGNOSTIC, "message": laws.SURVIVOR,
        }


def plan(spec, tag):
    for point in sorted(laws.finite_coords(spec["finite"])):
        value = laws.coordinate(tag, point)
        name = "/".join(point)
        yield from paired("finite", name, value) if value["legal"] else refused("finite", name, value)

    for seed in spec["exploration"]["seeds"]:
        for ordinal, value in enumerate(laws.explored(tag, seed, spec["exploration"]["casesPerSeed"])):
            name = f"{seed}/{ordinal}"
            if not value["legal"]:
                yield from refused("seed", name, value)
                plan = "with-decoy" if value["plan"] == "remove-both" else value["plan"]
                alternate = value["parents"][2] if value["side"] == "parent" else value["children"][2]
                value = laws.model(tag, value["side"], "exact", plan, value["scope"], value["offset"],
                                   alternate["descending"], not alternate["visible"], value["parentFields"][0] == "b")
            yield from paired("seed", name, value)

    positive = laws.model(tag, "parent", "exact", "single", "same")
    yield "negative/open-inbound-catalog", {**laws.analyze(positive), "complete": False}, None, {
        "exception": DIAGNOSTIC, "message": "Persistent index removal needs a complete native foreign-key catalog.",
    }
    for label, transform, explanation in (
        ("foreign-owner", lambda owner, keys: ([laws.CROSS, *owner[1:]], keys), "A foreign-key index requirement must name the selected persistent owner."),
        ("temporary-support", lambda owner, keys: ([*owner[:2], "temporary-table"], keys), "Foreign-key index support requires a persistent owning table."),
        ("duplicate-support", lambda owner, keys: (owner, [keys[0], keys[0].upper()]), "Foreign-key index support cannot repeat a physical key."),
        ("empty-support", lambda owner, keys: (owner, []), "Foreign-key index support needs one or more physical keys."),
    ):
        sample = laws.direct(positive)
        facts = sample["mysqlIndexForeignKeys"][0]
        owner, keys = transform(facts["owner"], facts["keys"])
        facts.update(owner=owner, keys=keys)
        yield f"negative/{label}", sample, None, {"exception": DIAGNOSTIC, "message": explanation}


def requests(engine, tag, spec):
    entries = list(plan(spec, tag))
    same(negative_count(spec, engine, tag), sum(verdict is not None for _, _, _, verdict in entries))
    yield from entries


def rejected_finite_ids(spec, tag):
    return {item["rejectionId"] for _, _, item, verdict in plan(spec, tag) if verdict is not None and item is not None}


def rejection_sql(item, _request):
    return laws.sql(item["model"])


def observe_rejection(_, item, actual, sql):
    require(not item["model"]["legal"], "mysql-index-foreign-keys-predicts-unexpected-refusal")
    check_observation(item, actual, sql)
    return item["rejectionId"]


def check_value(item, actual):
    model = item["model"]
    profile = model["release"] + PREFIX + laws.PROFILE[model["plan"]]
    field = laws.WIRE[model["plan"]]
    require(actual["status"] == "constructed", "mysql-index-foreign-keys-not-constructed", actual)
    same(profile, actual["profile"])
    same(laws.direct(model)[field], actual[field])


def coordinate_seen(actual, item):
    model = item["model"]
    field = laws.WIRE[model["plan"]]
    result = actual[field]
    first = (result if field == "mysqlDropIndex" else result["targets"][0] if field == "mysqlDropIndexes"
             else result["actions"][0]["drop"])
    schema, table, kind = first["owner"]
    side = "parent" if table == laws.PARENT else "child"
    scope = "shadow" if kind == "temporary-table" else "cross" if model["child"][0] == laws.CROSS else "same"
    if field == "mysqlDropIndexes":
        plan = "with-decoy" if result["targets"][1]["name"].startswith("w") else "remove-both"
    else:
        plan = "single" if field == "mysqlDropIndex" else "mixed"
    require(schema == model["owner"][0], "mysql-index-foreign-keys-wrong-owning-schema", actual)
    return side, model["shape"], plan, scope


def analysis_finite_id(_, actual, item, sql):
    same(laws.sql(item["model"], True), sql)
    return coordinate_seen(actual, item)


def direct_finite_id(_, actual, item, request):
    same(laws.direct(item["model"]), request)
    return coordinate_seen(actual, item)


def required_forms(_, tag):
    plan = laws.PLAN if tag == laws.NO_RENAME else (*laws.PLAN, "mixed")
    return {*(f"side/{side}" for side in laws.SIDE), *(f"plan/{kind}" for kind in plan),
            "scope/same", "scope/cross", "scope/shadow"}


def sample_forms(forms, actual, _, item):
    side, shape, plan, scope = coordinate_seen(actual, item)
    forms.update((f"side/{side}", f"shape/{shape}", f"plan/{plan}", f"scope/{scope}"))


def sample_native_forms(forms, actual, _, item):
    terms = [key for group in ("parents", "children", "session") for key in (actual["before"][group] or [])]
    forms.update((f"key-direction/{term[5]}" for term in terms))
    forms.update((f"key-visibility/{term[7]}" for term in terms))
    forms[f"reference-width/{len(actual['before']['edges'])}"] += 1


def observer_request(item, sql):
    model = item["model"]
    return {"sql": sql, "declarations": laws.declarations(model), "offset": model["offset"],
            "child": ".".join(model["child"]), "shadow": None if model["session"] is None else ".".join(model["owner"][:2])}


def observation(actual):
    return actual


def expected(item):
    model = item["model"]
    return {**laws.expected(model), "marker": 7, "active": False,
            "affected": int(model["scope"] == "shadow") if model["legal"] else None}


def check_observation(item, actual, sql):
    prediction = expected(item)
    witness = {"expected": prediction, "observed": actual, "sql": sql}
    require(actual.get("status") == laws.STATUS, "mysql-index-foreign-keys-unobserved", witness)
    require({key: actual.get(key) for key in prediction} == prediction, "meaning-mismatch", witness)
    if item["model"]["legal"]:
        require(actual["diagnostics"] == [], "meaning-mismatch", witness)
    else:
        require(any(note[:2] == ["Error", 1553] for note in actual["diagnostics"]), "meaning-mismatch", witness)


def outside():
    return ["DROP INDEX other ON sample", "TRUNCATE TABLE `ir_fixture`.`ir_ixfk_p`",
            "DROP INDEX `u0` ON `ir_fixture`.`ir_ixfk_p`; SELECT 1"]


def prepare(_, db):
    item = {"model": laws.model("mysql-9.1.0", "parent", "exact", "single", "same")}
    return {"outside": [db.request(observer_request(item, sql)) for sql in outside()]}


def check_preparation(_, _environment, facts):
    same({"outside": [{"status": "outside-observer-scope"}] * len(outside())}, facts)


def controls():
    item = {"model": laws.model("mysql-9.1.0", "parent", "exact", "single", "same")}
    positive = {**expected(item), "diagnostics": []}
    command = laws.sql(item["model"])
    check_observation(item, positive, command)
    controls = {"ignore-ordered-key": ("before", "parents"), "ignore-native-foreign-key": ("after", "edges")}
    detected = {}
    for label, (phase, observation) in controls.items():
        mutant = deepcopy(positive)
        mutant[phase][observation].pop()
        try:
            check_observation(item, mutant, command)
        except Failure as error:
            same("meaning-mismatch", error.signal)
            detected[label] = error.detail
        else:
            raise Failure("mysql-index-foreign-keys-missed-comparator-fault", label)
    return detected


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same(["executed"], witness["observed"]["effect"])


def faults(_, tag):
    model = laws.model(tag, "child", "exact", "with-decoy", "cross")
    return [RenderFault("keep-explicitly-removed-decoy", "sql-semantics-mysql/src/Ir/Schema/Index/DropIndexes.php",
                        "implode(', ', $actions)", "implode(', ', array_slice($actions, 0, 1))", {"model": model},
                        "meaning-mismatch", laws.direct(model))]


if __name__ == "__main__":
    main(sys.modules[__name__])
