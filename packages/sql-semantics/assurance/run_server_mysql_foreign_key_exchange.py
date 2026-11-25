"""Check native MySQL mixed foreign-key edits and physical replacements."""

from copy import deepcopy
from itertools import product
import json
import sys

import mysql_foreign_key_exchange_cases as laws
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_mysql_drop_index import DIAGNOSTIC, specifications as profiles
from sqlite_domain import RenderFault

LABEL = "mysql-foreign-key-exchange"
OBSERVER = "mysql_foreign_key_exchange_observer.php"
NAME = "mysql-foreign-key-exchange-evidence-v2.json"
CONTRACT = "92d71447548f0191a3f0b92edbe2e737a47f8cdf36175138b6cb7230130a63aa"
POLICY = "The checked InnoDB profile needs copying and a compatible lock to add a native foreign key."
REMOVAL = "The exchange removal must select an original native constraint on its owning table."
COMPARATOR_FAULTS = {
    "Discard physical width when a name was reused.": "same-name-wrong-width",
    "Retain the original referential edge after the exchange.": "keep-original-reference",
    "Keep the old name during equal-width implicit replacement.": "equal-width-stale-name",
}


def specifications():
    path = TOOL / NAME
    require(digest(path) == CONTRACT, "mysql-foreign-key-exchange-contract")
    spec = json.loads(path.read_text())
    for file, expected in spec["references"].items():
        require(digest(TOOL / file) == expected, "mysql-foreign-key-exchange-prior-contract", file)
    _, captured = profiles()
    same(captured["mysql"]["releases"], spec["releases"])
    same([], spec["exploration"]["allowedExclusions"])
    return spec, captured


def finite_ids(_, spec, tag):
    coordinates = set(laws.grid(spec))
    same(spec["finite"]["coordinates"], len(coordinates))
    # The legacy dictionary permits a repeated removal. No profile can reuse an original symbol.
    allowed = {point for point in coordinates if point[1] != "same-folded-name"
               and (not point[3] or tag in laws.OLD)}
    same(spec["admitted"]["legacy" if tag in laws.OLD else "modern"], len(allowed))
    return allowed


def positive(kind, label, model):
    direct_kind = {"finite": "direct", "seed": "direct-seed", "edge": "edge-direct"}[kind]
    yield f"{kind}/{label}", laws.analyzed(model), {"model": model}, None
    yield f"{direct_kind}/{label}", laws.direct(model), {"model": model}, None


def negative(kind, label, model):
    reason = {
        "duplicate-symbol": "A new foreign-key symbol must be unique in the owning native database.",
        "repeated-drop": "This MySQL release cannot repeat a foreign-key removal.",
        "copy-policy": "The algorithm and lock cannot remove native foreign keys on this table." if model["lock"] == "NONE" else POLICY,
    }[model["error"]]
    for route, constructor in (("analysis", laws.analyzed), ("direct", laws.direct)):
        identity = f"{kind}/{label}/{route}"
        yield f"negative/{identity}", constructor(model), {"model": model, "identity": identity}, {
            "exception": DIAGNOSTIC, "message": reason,
        }


def edges(tag, specification):
    for kind in ("exchange", "add", "copy"):
        for name, mode in ((None, "allocated"), ("code_id", "distinct-explicit"), ("Widened", "distinct-explicit")):
            key = f"{kind}/{name or 'automatic'}"
            yield key, laws.model(tag, requested=mode, composite=True, origin="implicit", kind=kind, name=name)
    yield "last-slot-replaces-implicit", laws.model(tag, composite=True, origin="implicit", padding=62, name="Widened")

    plan = specification["prefixWitnesses"]
    points = tuple(product(plan["operations"], plan["prefixes"], plan["names"]))
    same(plan["coordinates"], len(set(points)))
    for kind, prefix, name in points:
        mode = "allocated" if name == "automatic" else "distinct-explicit"
        key = "code_id" if name == "reused" else "New_Support"
        yield f"{kind}/{prefix}/{name}", laws.model(
            tag, requested=mode, origin="implicit", kind=kind, name=key, prior_composite=prefix == "longer-prior",
        )


def invalid(tag):
    model = laws.model(tag, requested="distinct-explicit")
    base = laws.direct(model)
    path = "mysqlAlterForeignKeys"
    previous = model["old"]
    yield "missing-origin", {**base, "mysqlForeignKeyCatalog": {**base["mysqlForeignKeyCatalog"], "constraints": []}}, REMOVAL
    mismatch = deepcopy(base)
    mismatch["mysqlForeignKeyCatalog"]["constraints"][0][0] = [laws.SCHEMA, "other", "table"]
    yield "wrong-origin", mismatch, REMOVAL
    for label, action, message in (
        ("unknown-symbol", {"drop": ["Unknown", "constraint"]}, REMOVAL),
        ("unresolved-spelling", {"drop": [previous.upper(), "constraint"]}, REMOVAL),
        ("ignored-removal", {"drop": [None, "ignored-on-temporary"]},
         "The foreign-key action does not belong to this native table lifetime."),
        ("wrong-kind", 19, "A foreign-key exchange requires checked addition and removal roles."),
    ):
        changed = deepcopy(base)
        changed[path]["actions"][0] = action
        yield label, changed, message
    for index, label in enumerate(("only-drop", "only-add")):
        changed = deepcopy(base)
        changed[path]["actions"] = [base[path]["actions"][index]]
        yield label, changed, "A foreign-key exchange requires both additions and removals."
    changed = deepcopy(base)
    changed[path]["actions"] = {"3": base[path]["actions"][0], "4": base[path]["actions"][1]}
    yield "sparse-clauses", changed, "A foreign-key exchange needs a nonempty ordered action list."
    for prior_is_longer, wrong in ((False, None), (True, "Forced_Change")):
        model = laws.model(tag, requested="distinct-explicit", origin="implicit", kind="add",
                           name="Forced_Change", prior_composite=prior_is_longer)
        request = laws.direct(model)
        request["mysqlAddForeignKeys"]["additions"][0]["created"] = wrong
        yield ("force-shorter-index" if prior_is_longer else "deny-equal-replacement"), request, (
            "The declared foreign-key index effect differs from native allocation.")
    absent = deepcopy(base)
    absent["mysqlForeignKeyCatalog"]["keys"] = [
        key for key in absent["mysqlForeignKeyCatalog"]["keys"] if key["owner"] == laws.OWNER
    ]
    yield "unsupported-parent", absent, "A native foreign key requires original physical support on the resolved parent."


def requests(engine, tag, spec):
    negatives = 0
    for coordinate in laws.grid(spec):
        label = "/".join(map(str, coordinate))
        model = laws.model(tag, *coordinate)
        if model["error"] is None:
            yield from positive("finite", label, model)
        else:
            negatives += 2
            yield from negative("finite", label, model)
    for name, model in edges(tag, spec):
        yield from positive("edge", name, model)
    for name, model in (("in-place", laws.model(tag, algorithm="INPLACE")),
                        ("unlocked-copy", laws.model(tag, algorithm="COPY", lock="NONE"))):
        negatives += 2
        yield from negative("edge", name, model)
    for name, request, message in invalid(tag):
        negatives += 1
        yield f"negative/direct/{name}", request, None, {"exception": DIAGNOSTIC, "message": message}
    same(negative_count(spec, engine, tag), negatives)
    for seed in spec["exploration"]["seeds"]:
        for ordinal, model in enumerate(laws.exploratory(tag, seed, spec["exploration"]["casesPerSeed"], spec)):
            yield from positive("seed", f"{seed}/{ordinal}", model)


def rejection_sql(item, _):
    return laws.statement(item["model"], True)


def rejected_finite_ids(spec, tag):
    known = {f"finite/{'/'.join(map(str, point))}/{route}" for point in laws.grid(spec)
             if point[1] == "same-folded-name" or (point[3] and tag not in laws.OLD)
             for route in ("analysis", "direct")}
    known.update(f"edge/{edge}/{route}" for edge in ("in-place", "unlocked-copy") for route in ("analysis", "direct"))
    return known


def check_value(item, response):
    model = item["model"]
    expected = laws.shapes(model)
    kind = "mysqlAlterForeignKeys" if model["kind"] == "exchange" else "mysqlAddForeignKeys"
    profile = laws.PROFILE if model["kind"] == "exchange" else laws.ADD_PROFILE
    require(response.get("status") == "constructed", "mysql-foreign-key-exchange-not-constructed", response)
    same(model["release"] + profile, response["profile"])
    same({**expected[kind], "removedIndexes": model["removed"]}, response[kind])


def measured_coordinate(item, response):
    model = item["model"]
    value = response["mysqlAlterForeignKeys"]
    actions = value["actions"]
    removals = [entry["drop"][0] for entry in actions if "drop" in entry]
    first_addition = next(entry["add"] for entry in actions if "add" in entry)
    expected_symbol = {"Original": "explicit", laws.TABLE + "_ibfk_1": "ordinal-one", laws.TABLE + "_ibfk_9": "ordinal-nine"}
    return (expected_symbol[removals[0]], "allocated" if first_addition["generated"] else "distinct-explicit",
            "drop-before-add" if "drop" in actions[0] else "add-before-drop", len(removals) > 1,
            model["retained"], value["policy"][0])


def analysis_finite_id(_, response, item, sql):
    same(laws.statement(item["model"], True), sql)
    return measured_coordinate(item, response)


def direct_finite_id(_, response, item, request):
    same(laws.direct(item["model"]), request)
    return measured_coordinate(item, response)


def required_forms(_, _tag):
    return {"additions/1", "additions/2", "arity/1", "arity/2", "scope/same", "scope/cross",
            "allocated/True", "allocated/False", "order/drop", "order/add"}


def sample_forms(forms, response, _, item):
    value = response["mysqlAlterForeignKeys"]
    actions = value["actions"]
    added = [entry["add"] for entry in actions if "add" in entry]
    forms[f"additions/{len(added)}"] += 1
    forms["order/drop" if "drop" in actions[0] else "order/add"] += 1
    for addition in added:
        forms[f"arity/{len(addition['pairs'])}"] += 1
        forms[f"allocated/{addition['generated']}"] += 1
        forms["scope/same" if addition["parent"][0] == value["owner"][0] else "scope/cross"] += 1


def observer_request(item, sql):
    model = item["model"]
    return {"sql": sql, "parent": model["parent"][0], "padding": model["padding"],
            "partial": model["kind"] != "exchange", "declarations": laws.ddl(model)}


def observation(value):
    return value


def check_observation(item, actual, sql):
    model = item["model"]
    expected = laws.prediction(model)
    effect = expected["effect"]
    expected["affected"] = len(laws.ROWS) if effect == ["executed"] else None
    witness = {"expected": expected, "observed": actual, "sql": sql}
    require(actual.get("status") == laws.STATUS, "mysql-foreign-key-exchange-not-observed", witness)
    require({key: actual.get(key) for key in expected} == expected, "meaning-mismatch", witness)
    diagnostics = [entry[:2] for entry in actual["diagnostics"]]
    terminal = [] if effect == ["executed"] else [["Error", effect[2]]]
    require(diagnostics == terminal, "meaning-mismatch", witness)


def observe_rejection(_, item, actual, sql):
    check_observation(item, actual, sql)
    return item["identity"]


def prepare(_, db):
    item = {"model": laws.model("mysql-9.1.0")}
    sql = laws.statement(item["model"], True)
    invalid = (sql + "; SELECT 1", sql.replace(laws.TABLE, "unrelated"), sql.replace(laws.PARENT, "other"))
    return [db.request(observer_request(item, statement)) for statement in invalid]


def check_preparation(_, _environment, observed):
    same([{"status": "outside-observer-scope"}] * 3, observed)


def controls():
    item = {"model": laws.model("mysql-9.1.0", composite=True, origin="implicit")}
    actual = {**laws.prediction(item["model"]), "affected": len(laws.ROWS), "diagnostics": []}
    sql = laws.statement(item["model"], True)
    check_observation(item, actual, sql)
    width = deepcopy(actual)
    width["after"]["keys"] = [term for term in width["after"]["keys"] if term[:4] != ["code_id", 1, 2, "peer_id"]]
    edge = deepcopy(actual)
    edge["after"]["fields"] = [*edge["after"]["fields"], *edge["before"]["fields"]]
    records = {}
    for name, value in (("same-name-wrong-width", width), ("keep-original-reference", edge)):
        try:
            check_observation(item, value, sql)
        except Failure as failure:
            same("meaning-mismatch", failure.signal)
            records[name] = failure.detail
        else:
            raise Failure("mysql-foreign-key-exchange-fault-missed", name)
    equal = {"model": laws.model("mysql-9.1.0", requested="distinct-explicit", origin="implicit", kind="add",
                                 name="New_Support")}
    actual = {**laws.prediction(equal["model"]), "affected": len(laws.ROWS), "diagnostics": []}
    sql = laws.statement(equal["model"], True)
    check_observation(equal, actual, sql)
    stale = deepcopy(actual)
    for key in stale["after"]["keys"]:
        if key[0] == "New_Support":
            key[0] = "code_id"
    try:
        check_observation(equal, stale, sql)
    except Failure as failure:
        same("meaning-mismatch", failure.signal)
        records["equal-width-stale-name"] = failure.detail
    else:
        raise Failure("mysql-foreign-key-exchange-fault-missed", "equal-width-stale-name")
    return records


def faults(_, tag):
    role = "sql-semantics-mysql/src/Ir/Schema/ForeignKey/"
    witness = laws.model(tag, requested="distinct-explicit", composite=True)
    context = {"model": witness}
    return [
        RenderFault("erase-exchange-removal", role + "Exchange/AlterForeignKeys.php",
                    "$actions[] = $action->toString();",
                    "if ($action instanceof ForeignKeyAddition) { $actions[] = $action->toString(); }",
                    context, "meaning-mismatch", laws.direct(witness)),
        RenderFault("swap-exchange-parent-events", role + "Add/ForeignKeyEffects.php",
                    "$this->update->value . ' ON DELETE ' . $this->delete->value",
                    "$this->delete->value . ' ON DELETE ' . $this->update->value",
                    context, "meaning-mismatch", laws.direct(witness)),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
