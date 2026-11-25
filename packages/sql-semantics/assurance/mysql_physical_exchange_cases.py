"""Finite coordinate law for a native atomic physical and foreign-key change."""

from itertools import product
from random import Random

AXES = {
    "outgoing": (("implicit", "explicit"), ("no-physical", "same-name", "other-name", "opposite-direction", "non-leading"),
                 ("same-side", "other-side"), ("False", "True"), ("drop-first", "add-first")),
    "incoming": (("ordinary", "unique", "same-name-unique", "wider-unique", "descending-unique", "absent"),
                 ("shared", "separate"), ("drop-first", "add-first")),
    "generating": (("False", "True"), ("secondary", "primary"), ("none", "same-name", "ascending", "descending", "later"),
                   ("generation", "other"), ("drop-first", "add-first")),
    "self-parent": (("unique", "ordinary", "unindexed"),
                    ("none", "ordinary", "unique", "descending-unique", "wider-unique", "same-name-unique"),
                    ("drop-first", "add-first")),
    "reclustering": (("no-unique", "retain-asc", "retain-null", "add-asc", "add-desc", "add-null"),
                     ("only-short", "ordinary-exact", "unique-exact", "wrong-leading"),
                     ("drop-first", "add-first")),
}


def coordinates():
    return ["/".join((family, *point)) for family, axes in AXES.items() for point in product(*axes)]


def analysis(case, release):
    return {"dialect": "mysql", "release": "mysql-" + release, "declarations": case["declarations"],
            "currentDatabase": "ir_fixture", "sql": case["sql"]}


def expected_value(packet, cluster=None):
    expected = packet["mysqlAlterForeignKeys"]
    actions = []
    for item in expected["actions"]:
        if "physicalDrop" in item:
            key = item["physicalDrop"]
            actions.append({"physicalDrop": {field: key[field] for field in ("owner", "name", "role")}})
        elif "index" in item:
            key = item["index"]
            actions.append({"index": {field: key[field] for field in ("owner", "name", "role", "automatic", "fields")}})
        else:
            actions.append(item)
    return {"owner": expected["owner"], "actions": actions, "removedIndexes": [],
            "policy": ["DEFAULT", "DEFAULT"], "ignore": False,
            "clusteredReplacement": None if cluster is None else {"owner": expected["owner"], "name": cluster, "role": "clustered-unique"}}


def clustered_replacement(case):
    family, choice, alternative, *_ = case["id"].split("/")
    if family != "reclustering" or choice.endswith("-null"):
        return None
    if choice == "retain-asc":
        return "retained"
    if alternative == "unique-exact":
        return "substitute"
    return "promoted" if choice in ("add-asc", "add-desc") else None


def explorations(cases, tag, seed, count, model_release=None):
    rng = Random(seed)
    pools = {name: [case for case in cases if case["id"].startswith(name + "/")
                    and case["outcomes"][model_release or tag]["alter"] == "ok"] for name in AXES}
    # These columns are outside every required child, parent and generating prefix
    # of the corresponding finite family.
    neutral = {"outgoing": "id", "incoming": "p", "generating": "id", "self-parent": "extra", "reclustering": "id"}
    previous_families = ("outgoing", "incoming", "generating", "self-parent")
    for position in range(count):
        family = previous_families[position % len(previous_families)] if position < 24 else "reclustering"
        case = rng.choice(pools[family])
        field = neutral[family]
        # In the generation case, an original primary on id can coexist with
        # duplicate ordinary definitions on id. Their warnings are compared.
        extra = [(f"seed_{seed}_{position}_{slot}", field, bool(rng.randrange(2)))
                 for slot in range(1 + position % 2)]
        sql = case["sql"] + "".join(f", ADD KEY {name}({term}{' DESC' if desc else ''})" for name, term, desc in extra)
        yield {**case, "sql": sql, "extra": extra}


def key(owner, name, role, *fields, implicit=False):
    return {"owner": owner, "name": name, "role": role, "full": True, "implicitForeignKey": implicit,
            "fields": [[term, False] if isinstance(term, str) else term for term in fields]}


def extras(case, owner, old):
    return [{"index": key(owner, name, "ordinary", [field, descending and not old]) | {"automatic": False}}
            for name, field, descending in case.get("extra", [])]


def reclustering(case, release):
    _, choice, alternative, order = case["id"].split("/")
    old = release in ("5.6.51", "5.7.44")
    owner, parent, child = [["ir_fixture", name, "table"] for name in ("affected", "upstream", "dependant")]
    required = choice not in ("retain-null", "add-null")
    primary = key(owner, "PRIMARY", "primary", "id")
    source = key(owner, "source", "unique", "cand", "hid") | {"clusterEligible": required}
    original = [primary, source, key(owner, "fallback", "ordinary", "cand")]
    if choice.startswith("retain"):
        original.append(key(owner, "retained", "unique", "hid") | {"clusterEligible": required})
    original.append(key(parent, "PRIMARY", "primary", "id"))
    whole = "int-not-null" if required else "int"
    heading = [["cand", "int-not-null"], ["hid", whole]]
    add = []
    if alternative != "only-short":
        kind = "unique" if alternative == "unique-exact" else "ordinary"
        fields = ("hid", "cand") if alternative == "wrong-leading" else ("cand", "hid")
        add.append({"index": key(owner, "substitute", kind, *fields) | {"heading": heading, "automatic": False}})
    if choice.startswith("add"):
        direction = choice == "add-desc" and not old
        add.append({"index": key(owner, "promoted", "unique", ["hid", direction]) | {"heading": heading, "automatic": False}})
    action = "RESTRICT" if old else "NO ACTION"
    add.append({"add": {"parent": parent, "symbol": "outbound", "generated": False, "indexName": None, "created": "outbound",
                        "pairs": [["probe", "id", "INTEGER", False, True, False]], "effects": [action, action, "NONE"]}})
    remove = [{"physicalDrop": fact | {"autoIncrement": None}} for fact in (primary, source)]
    actions = remove + add if order == "drop-first" else add + remove
    actions.extend(extras(case, owner, old))
    return {"dialect": "mysql", "release": "mysql-" + release,
            "mysqlAlterForeignKeys": {"owner": owner, "actions": actions,
                "remainingSides": [[True, ["cand", "hid"], child, "incoming"]],
                "policy": ["DEFAULT", "DEFAULT"], "ignore": False},
            "mysqlForeignKeyCatalog": {"keys": original, "constraints": [[child, "incoming"]]}}


def direct(case, release):
    old = release in ("5.6.51", "5.7.44")
    family, *axis = case["id"].split("/")
    if family == "reclustering":
        return reclustering(case, release)
    owner_name = {"outgoing": "fk_test_child", "incoming": "fk_test_parent",
                  "generating": "affected", "self-parent": "affected"}[family]
    parent_name = {"outgoing": "fk_test_parent", "incoming": "fk_test_root",
                   "generating": "reference_values", "self-parent": "affected"}[family]

    owner, parent = [["ir_fixture", name, "table"] for name in (owner_name, parent_name)]
    order = axis[-1]
    primary = "id"
    parent_field = "p"
    sides = []
    prior = []
    other = []
    increment = None
    drop_fk = None
    declared = None
    remains = []
    if family == "outgoing":
        origin, shape, relationship, removed, _ = axis
        symbol = "replacement"
        field = "pid" if relationship == "same-side" else "extra"
        parent_field = "p" if relationship == "same-side" else "b"
        anchor = key(owner, "anchor", "ordinary", "pid", implicit=origin == "implicit")
        prior.append([owner, "anchor"])
        if removed == "True":
            drop_fk = "anchor"
        else:
            sides.append([False, ["pid"], owner, "anchor"])
        declared = {
            "no-physical": None, "same-name": key(owner, "anchor", "ordinary", "pid"),
            "other-name": key(owner, "alternate", "ordinary", "pid", "extra"),
            "opposite-direction": key(owner, "alternate", "ordinary", ["pid", not old]),
            "non-leading": key(owner, "alternate", "ordinary", "extra", "pid"),
        }[shape]
        other.extend((key(parent, "PRIMARY", "primary", "p"), key(parent, "b", "unique", "b")))
    elif family == "incoming":
        shape, child_source, _ = axis
        primary = "p"
        symbol = "outgoing"
        field = "cand" if child_source == "shared" else "extra"
        anchor = key(owner, "anchor", "unique", "cand")
        child = ["ir_fixture", "fk_test_child", "table"]
        prior.append([child, "incoming"])
        sides.append([True, ["cand"], child, "incoming"])
        declared = {
            "ordinary": key(owner, "substitute", "ordinary", "cand"),
            "unique": key(owner, "substitute", "unique", "cand"),
            "same-name-unique": key(owner, "anchor", "unique", "cand"),
            "wider-unique": key(owner, "substitute", "unique", "cand", "extra"),
            "descending-unique": key(owner, "substitute", "unique", ["cand", not old]),
            "absent": None,
        }[shape]
        other.append(key(parent, "PRIMARY", "primary", "p"))
    elif family == "generating":
        alternate, original, shape, relationship, _ = axis
        symbol = "link"
        field = "seq" if relationship == "generation" else "pid"
        primary = "seq" if original == "primary" else "id"
        anchor = key(owner, "PRIMARY" if original == "primary" else "anchor", "primary" if original == "primary" else "ordinary", "seq")
        alternate = alternate == "True"
        increment = {"field": "seq", "alternates": ["supporting"] if alternate else []}
        if alternate:
            remains.append(key(owner, "supporting", "ordinary", "seq", "id"))
        label = "anchor" if original == "secondary" else "replacement_key"
        declared = {
            "none": None, "same-name": key(owner, label, "ordinary", "seq"),
            "ascending": key(owner, "replacement_key", "ordinary", "seq", "pid"),
            "descending": key(owner, "replacement_key", "ordinary", ["seq", not old]),
            "later": key(owner, "replacement_key", "ordinary", "pid", "seq"),
        }[shape]
        other.append(key(parent, "PRIMARY", "primary", "p"))
    else:
        original, shape, _ = axis
        symbol, field = "local_edge", "c"
        anchor = key(owner, "anchor", "unique" if original == "unique" else "ordinary",
                     "extra" if original == "unindexed" else "p")
        declared = {
            "none": None, "ordinary": key(owner, "replacement_key", "ordinary", "p"),
            "unique": key(owner, "replacement_key", "unique", "p"),
            "descending-unique": key(owner, "replacement_key", "unique", ["p", not old]),
            "wider-unique": key(owner, "replacement_key", "unique", "p", "extra"),
            "same-name-unique": key(owner, "anchor", "unique", "p"),
        }[shape]

    primary_key = key(owner, "PRIMARY", "primary", primary)
    retained = (remains if anchor["name"] == "PRIMARY" else [primary_key, *remains])
    coverage = retained + ([] if declared is None else [declared])
    created = None if any(item["fields"][0] == [field, False] for item in coverage) else symbol
    default = "RESTRICT" if old else "NO ACTION"
    effects = [default, default, "NONE"] if family == "generating" else ["CASCADE", "CASCADE", "NONE"]
    nullable = field != "seq" and field != "cand"
    addition = {"parent": parent, "symbol": symbol, "generated": False, "indexName": None, "created": created,
                "pairs": [[field, parent_field, "INTEGER", False, nullable, False]], "effects": effects}
    lowered = [{"physicalDrop": anchor | {"autoIncrement": increment}}]
    if drop_fk:
        lowered.append({"drop": [drop_fk, "constraint"]})
    added = ([] if declared is None else [{"index": declared | {"automatic": False}}]) + [{"add": addition}]
    actions = lowered + added if order == "drop-first" else added + lowered
    actions.extend(extras(case, owner, old))
    return {"dialect": "mysql", "release": "mysql-" + release,
            "mysqlAlterForeignKeys": {"owner": owner, "actions": actions, "remainingSides": sides,
                                     "policy": ["DEFAULT", "DEFAULT"], "ignore": False},
            "mysqlForeignKeyCatalog": {"keys": [anchor, *retained, *other], "constraints": prior}}
