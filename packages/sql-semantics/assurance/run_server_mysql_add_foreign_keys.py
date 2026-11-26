"""Replay native MySQL foreign-key addition, index allocation and row effects."""

from copy import deepcopy
import json
import sys

import mysql_add_foreign_keys_cases as laws
from run import TOOL, Failure, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_mysql_drop_index import DIAGNOSTIC, specifications as native_profiles
from sqlite_domain import RenderFault

LABEL = "mysql-add-foreign-keys"
OBSERVER = "mysql_add_foreign_keys_observer.php"
NAME = "mysql-add-foreign-keys-evidence-v3.json"
BASE = "214b04864ac045b8ee9b10e5291a5d59c5f34d893df871dd3de791d7ed6bb09a"
CONTRACT = "ada4f2890c01347741ae019ba709c825d20e84d0b645ca3ec165c20a8bc5f81c"
COMPARATOR_FAULTS = {
    "Erase native MATCH metadata with unchanged rows.": "erase-match-metadata",
    "Erase an observed partial-NULL insertion outcome.": "erase-partial-null-effects",
}
POLICY = "The checked InnoDB profile needs copying and a compatible lock to add a native foreign key."
SUPPORT = "A native foreign key requires original physical support on the resolved parent."


def specifications():
    base, exact = TOOL / "mysql-add-foreign-key-groups-v1.json", TOOL / NAME
    require(digest(base) == BASE and digest(exact) == CONTRACT, "mysql-add-foreign-key-contract")
    law, spec = json.loads(base.read_text()), json.loads(exact.read_text())
    for name, expected in spec["publicationContracts"].items():
        require(digest(TOOL / name) == expected, "mysql-add-foreign-key-publication-contract", name)
    _, profiles = native_profiles()
    same(law["language"]["releases"], spec["releases"])
    same(profiles["mysql"]["releases"], spec["releases"])
    same(law["boundedPlan"]["exploration"], spec["exploration"])
    for filename, sha in law["references"].items():
        require(digest(TOOL / filename) == sha, "mysql-add-foreign-key-action-contract", filename)
    return spec, profiles


def finite_ids(_, spec, tag):
    identities = laws.finite_coords(spec)
    same(spec["finiteIdentities"], len(identities))
    accepted = {point for point in identities if laws.coordinate(tag, point)["violation"] is None}
    same(spec["admitted"][tag], len(accepted))
    return accepted


def pair(axis, name, model):
    item = {"model": model}
    direct = {"finite": "direct", "seed": "direct-seed", "authored": "direct-authored"}[axis]
    yield f"{axis}/{name}", laws.analyze(model), item, None
    yield f"{direct}/{name}", laws.direct(model), item, None


def diagnostic(model, route):
    boundary = {
        "legacy-direction": ("A native foreign key requires full fields and release-appropriate ordering.",
                             "Legacy MySQL foreign keys do not accept native index direction."),
        "legacy-set-default": ("Legacy InnoDB rejects effective SET DEFAULT on a foreign key.",) * 2,
        "required-set-null": ("SET NULL requires nullable native child fields.",) * 2,
        "parent-key": (SUPPORT,) * 2,
        "copy-policy": (POLICY,) * 2,
    }[model["violation"]]
    return boundary[route == "direct"]


def rejected(axis, name, model):
    for route, builder in (("analysis", laws.analyze), ("direct", laws.direct)):
        identity = f"{axis}/{name}/{route}"
        yield f"negative/{identity}", builder(model), {"model": model, "rejectionId": identity}, {
            "exception": DIAGNOSTIC, "message": diagnostic(model, route),
        }


def directed(tag):
    yield "retained-match-partial-order", laws.model(tag, "paired", "symbol", "match-partial", "new-key", scope="parent-shadow")
    yield "legacy-ignored-effects-before-validation", laws.model(
        tag, "paired", "written-key", "set-default-under-match", "original-key", required=True)
    yield "orthogonal-native-data-refusal", laws.model(tag, "single", "automatic", "default", "new-key", wrong_rows=True)
    yield "compatible-nonunique-original-parent", laws.model(
        tag, "paired", "symbol", "match-simple", "original-key", scope="cross", parent_key="nonunique")
    yield "absent-original-parent", laws.model(tag, "single", "symbol", "default", "new-key", scope="cross", parent_key="absent")
    yield "required-child-cannot-nullify", laws.model(tag, "single", "symbol", "cascade-set-null", "new-key", required=True)
    yield "copy-requires-lock", laws.model(tag, "single", "symbol", "default", "new-key", policy=("COPY", "NONE"))
    yield "inplace-with-active-reference-checks", laws.model(
        tag, "single", "symbol", "default", "original-key", policy=("INPLACE", "DEFAULT"))
    yield "descending-physical-child-index", laws.model(tag, "paired", "symbol", "default", "new-key", descending=True)


def malformed(tag):
    sample = laws.model(tag, "paired", "automatic", "default", "new-key")
    yield "open-catalog", {**laws.analyze(sample), "complete": False}, "Native foreign-key addition needs a complete catalog in the default InnoDB profile."
    original = laws.direct(sample)
    for label, mutator, message in (
        ("wrong-native-generated-name", lambda s: s["mysqlAddForeignKeys"]["additions"][0].update(symbol="wrong_1"),
         "The foreign-key symbol differs from native allocation."),
        ("misstated-created-key", lambda s: s["mysqlAddForeignKeys"]["additions"][0].update(created="wrong_key"),
         "The declared foreign-key index effect differs from native allocation."),
        ("outside-physical-reference-owner", lambda s: s["mysqlAddForeignKeys"]["additions"][0].update(parent=[laws.ALT,laws.PARENT,"table"]),
         SUPPORT),
        ("missing-prior-key-list", lambda s: s["mysqlForeignKeyCatalog"].update(keys=[]), SUPPORT),
        ("cross-table-symbol-collision", lambda s: s["mysqlForeignKeyCatalog"].update(
            constraints=[[[laws.DB, "separate_table", "table"], sample["symbols"][0]]]),
         "A new foreign-key symbol must be unique in the owning native database."),
        ("repeating-paired-column", lambda s: s["mysqlAddForeignKeys"]["additions"][0]["pairs"].append(
            s["mysqlAddForeignKeys"]["additions"][0]["pairs"][0]),
         "A native foreign key cannot repeat a field on either side."),
    ):
        current = deepcopy(original)
        mutator(current)
        yield label, current, message


def plan(spec, tag):
    for point in sorted(laws.finite_coords(spec)):
        model = laws.coordinate(tag, point)
        name = "/".join(point)
        yield from pair("finite", name, model) if model["violation"] is None else rejected("finite", name, model)
    for seed in spec["exploration"]["seeds"]:
        for index, model in enumerate(laws.explored(tag, seed, spec["exploration"]["casesPerSeed"])):
            name = f"{seed}/{index}"
            if model["violation"] is not None:
                yield from rejected("seed", name, model)
                model = laws.model(tag, model["shape"], model["style"], "no-action", model["coverage"],
                                   scope=model["scope"], offset=model["offset"], policy=model["policy"], ignore=model["ignore"])
            yield from pair("seed", name, model)
    for name, model in directed(tag):
        yield from pair("authored", name, model) if model["violation"] is None else rejected("authored", name, model)
    for name, request, reason in malformed(tag):
        yield f"negative/boundary/{name}", request, None, {"exception": DIAGNOSTIC, "message": reason}


def requests(engine, tag, spec):
    cases = list(plan(spec, tag))
    same(negative_count(spec, engine, tag), sum(boundary is not None for _, _, _, boundary in cases))
    yield from cases


def rejected_finite_ids(spec, tag):
    return {item["rejectionId"] for _, _, item, boundary in plan(spec, tag) if boundary is not None and item is not None}


def rejection_sql(item, _):
    return laws.sql(item["model"], True)


def observe_rejection(_, item, actual, sql):
    require(item["model"]["violation"] is not None, "mysql-add-foreign-key-refusal-predicted-admitted")
    check_observation(item, actual, sql)
    return item["rejectionId"]


def check_value(item, actual):
    model = item["model"]
    require(actual.get("status") == "constructed", "mysql-add-foreign-key-not-constructed", actual)
    same(model["release"] + laws.PROFILE, actual["profile"])
    same({**laws.direct(model)["mysqlAddForeignKeys"], "removedIndexes": []}, actual["mysqlAddForeignKeys"])


def coordinate_seen(item, actual):
    model = item["model"]
    values = actual["mysqlAddForeignKeys"]["additions"]
    head = values[0]
    shape = "siblings" if len(values) == 2 else "paired" if len(head["pairs"]) == 2 else "single"
    name = "symbol" if not head["generated"] else "written-key" if head["indexName"] is not None else "automatic"
    coverage = "new-key" if head["created"] is not None else "original-key"
    # Native metadata observes the resulting labels and effects. Orthogonal source
    # coordinates can settle to the same value, especially ignored legacy MATCH.
    style = model["style"] if coverage == "original-key" and head["generated"] else name
    return shape, style, model["effects"], coverage


def analysis_finite_id(_, actual, item, sql):
    same(laws.sql(item["model"], True), sql)
    return coordinate_seen(item, actual)


def direct_finite_id(_, actual, item, request):
    same(laws.direct(item["model"]), request)
    return coordinate_seen(item, actual)


def required_forms(_, tag):
    result = {"shape/single", "shape/paired", "shape/siblings", "parent/same", "parent/cross", "physical/created",
              "physical/reused", "symbol/explicit", "symbol/generated", "effect/blocks", "effect/cascades", "effect/nullifies",
              "row/partial-admitted", "row/partial-refused"}
    if tag not in laws.LEGACY:
        result.update(("direction/descending", "match/FULL", "match/PARTIAL", "effect/SET DEFAULT"))
    return result


def sample_forms(forms, actual, _, item):
    model = item["model"]
    shape, _, _, _ = coordinate_seen(item, actual)
    forms[f"shape/{shape}"] += 1
    for value in actual["mysqlAddForeignKeys"]["additions"]:
        forms["parent/cross" if value["parent"][0] != model["owner"][0] else "parent/same"] += 1
        forms["physical/created" if value["created"] is not None else "physical/reused"] += 1
        forms["symbol/generated" if value["generated"] else "symbol/explicit"] += 1
        if any(pair[-1] for pair in value["pairs"]):
            forms["direction/descending"] += 1


def sample_native_forms(forms, actual, _, item):
    for _, update, delete, match in actual["rules"]:
        forms[f"match/{match}"] += 1
        for operation in (update, delete):
            forms[f"effect/{operation}"] += 1
            forms[{"CASCADE": "effect/cascades", "SET NULL": "effect/nullifies"}.get(operation, "effect/blocks")] += 1
    admitted = actual["probes"]["partial"][0] == "executed"
    forms["row/partial-admitted" if admitted else "row/partial-refused"] += 1


def observer_request(item, sql):
    model = item["model"]
    return {"sql": sql, "declarations": laws.declarations(model), "scope": model["scope"], "wrongRows": model["wrongRows"]}


def observation(actual):
    return actual


def refusal(model):
    reason, tag = model["violation"], model["release"]
    if model["wrongRows"]:
        return ["db-error", "23000", 1452]
    if reason == "copy-policy":
        return ["db-error", "0A000", 1846]
    if reason == "parent-key":
        return ["db-error", "HY000", 1215 if tag in laws.LEGACY else 6125 if tag in laws.STRICT_PARENT else 1822]
    if reason == "required-set-null":
        return ["db-error", "HY000", 1215 if tag in laws.LEGACY else 1830]
    if reason is not None:
        return ["db-error", "HY000", 1215]
    return ["executed"]


def check_observation(item, actual, sql):
    model = item["model"]
    effect = refusal(model)
    expected = {**laws.expected(model), "effect": effect, "settings": [1, 1], "active": False, "marker": 7,
                "affected": 1 if effect == ["executed"] else None}
    witness = {"expected": expected, "observed": actual, "sql": sql}
    require(actual.get("status") == laws.STATUS, "mysql-add-foreign-key-unobserved", witness)
    require({key: actual.get(key) for key in expected} == expected, "meaning-mismatch", witness)
    prefix = [["Warning", 1681]] if model["ignore"] else []
    terminal = [] if effect == ["executed"] else [["Error", effect[2]]]
    warnings = [["Warning", 150]] if model["violation"] == "parent-key" and model["release"] in laws.LEGACY else []
    require([note[:2] for note in actual["diagnostics"]] == prefix + warnings + terminal, "meaning-mismatch", witness)


def prepare(_, db):
    item = {"model": laws.model("mysql-9.1.0", "single", "symbol", "default", "new-key")}
    valid = laws.sql(item["model"])
    invalid = [valid + "; SELECT 1", valid.replace(laws.CHILD, "unrelated"), valid.replace(laws.PARENT, "not_declared")]
    return [db.request(observer_request(item, sql)) for sql in invalid]


def check_preparation(_, _environment, observed):
    same([{"status": "outside-observer-scope"}] * 3, observed)


def controls():
    item = {"model": laws.model("mysql-9.1.0", "paired", "symbol", "match-full", "new-key")}
    actual = {**laws.expected(item["model"]), "settings": [1, 1], "active": False, "marker": 7, "affected": 1, "diagnostics": []}
    command = laws.sql(item["model"])
    check_observation(item, actual, command)
    no_match, null = deepcopy(actual), deepcopy(actual)
    no_match["rules"][0][-1] = "NONE"
    null["probes"]["partial"] = ["db-error", "23000", 1452]
    result = {}
    for label, mutant in (("erase-match-metadata", no_match), ("erase-partial-null-effects", null)):
        try:
            check_observation(item, mutant, command)
        except Failure as error:
            same("meaning-mismatch", error.signal)
            result[label] = error.detail
        else:
            raise Failure("mysql-add-foreign-key-control-missed", label)
    return result


def faults(_, tag):
    directory = "sql-semantics-mysql/src/Ir/Schema/ForeignKey/Add/"
    reference = laws.model(tag, "single", "symbol", "default", "new-key", scope="child-database")
    actions = laws.model(tag, "paired", "symbol", "cascade-set-null", "new-key")
    return [
        RenderFault("bind-parent-to-current-database", directory + "ForeignKeyAddition.php",
                    "$this->parent->quoted()", "'`ir_fk_add_alt`.`ir_fk_add_parent`'",
                    {"model": reference}, "meaning-mismatch", laws.direct(reference)),
        RenderFault("transpose-parent-events", directory + "ForeignKeyEffects.php",
                    "$this->update->value . ' ON DELETE ' . $this->delete->value",
                    "$this->delete->value . ' ON DELETE ' . $this->update->value",
                    {"model": actions}, "meaning-mismatch", laws.direct(actions)),
    ]


def check_fault_witness(item, witness):
    expected, actual = witness["expected"], witness["observed"]
    if item["model"]["scope"] == "child-database":
        same(["db-error", "23000", 1452], actual["effect"])
        same(["executed"], expected["effect"])
    else:
        same(["executed"], actual["effect"])
        require(actual["probes"]["update"] != expected["probes"]["update"]
                and actual["probes"]["delete"] != expected["probes"]["delete"], "mysql-add-foreign-key-fault-no-row-effect", witness)


if __name__ == "__main__":
    main(sys.modules[__name__])
