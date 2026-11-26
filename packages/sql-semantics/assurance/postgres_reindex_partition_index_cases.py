"""Author and explain named partitioned-index effects independently of candidate lowering."""

from postgres_reindex_cases import FORMS, qualified, quoted
from postgres_reindex_schema_cases import index
from postgres_reindex_partition_table_cases import expected as table_effect
from postgres_reindex_partition_table_cases import fixture as table_fixture
from postgres_reindex_partition_table_cases import path, trial as table_trial

TARGETS = {
    "root-empty": "root-empty", "root-flat": "root-two-leaves", "root-nested": "root-nested",
    "branch-remote": "branch-remote", "leaf-physical": "leaf-local",
}
STATUS = "partition-index-rebuild"


def trial(tag, selector, concurrent, active, spelling, *, root="orders", key="root_key", base=34, form=None, coordinate=None):
    item = table_trial(tag, TARGETS[selector], concurrent, active, spelling,
                       root=root, base=base, form=form, coordinate=coordinate)
    if selector == "branch-remote":
        schema, chosen, owner = "decoy", "high_key", "high"
    elif selector == "leaf-physical":
        schema, chosen, owner = "fixture", "payload", "high1"
    else:
        schema, chosen, owner = "fixture", key, root
    item.update(selector=selector, rootIndex=key, reindex={
        "target": [schema, chosen, owner],
        "mode": "concurrently" if concurrent else "standard",
        "partitionKind": "leaf" if selector == "leaf-physical" else "branch",
    })
    return item


def fixture(item, populate=False):
    catalog = table_fixture(item, populate=populate)
    return [*catalog, 'CREATE INDEX "high_key" ON "decoy"."high" (note)'] if item["layout"] == "nested" else catalog


def analyzed(item):
    schema, chosen, _ = item["reindex"]["target"]
    if item["spelling"] == "local":
        schema = None
    return {"dialect": "postgres", "release": item["release"], "declarations": fixture(item),
            "schemaPath": path(item), "sql": FORMS[item["form"]] + qualified(schema, chosen)}


def selected_indexes(item):
    selector = item["selector"]
    if selector == "leaf-physical":
        return {(qualified("fixture", "high1"), "payload")}
    if selector == "branch-remote":
        return {(qualified("fixture", "high1"), "payload"), (qualified("decoy", "high0"), "high0_note_idx")}
    if selector == "root-empty":
        return set()
    first = {(qualified("fixture", "low"), "low_id_idx")}
    if selector == "root-flat":
        return first | {(qualified("decoy", "high"), "high_id_idx")}
    return first | {(qualified("decoy", "high0"), "high0_id_idx"), (qualified("fixture", "high1"), "high1_id_idx")}


def expected(item):
    parent = table_effect(item)
    states = parent["before"]
    if item["layout"] == "nested":
        states[qualified("decoy", "high")]["indexes"].append(index([3], "high_key"))
        states[qualified("decoy", "high0")]["indexes"].append(index([3], "high0_note_idx"))
        supplement = [["decoy", "high_key", "fixture", "payload"], ["decoy", "high_key", "decoy", "high0_note_idx"]]
        for phase in ("attachmentsBefore", "attachmentsDuring", "attachmentsSettled"):
            if parent[phase] is not None:
                parent[phase] = sorted(parent[phase] + supplement)
    targets = selected_indexes(item)
    concurrent = item["reindex"]["mode"] == "concurrently"

    def delta(move):
        values = {}
        for shape, source in states.items():
            changes = []
            for constraint in source["indexes"]:
                selected = move and (shape, constraint["name"]) in targets
                changes.append([selected, selected and concurrent])
            values[shape] = {"indexes": changes, "toastIndexes": [[False, False] for _ in source["toastIndexes"]]}
        return values

    return {**parent, "status": STATUS, "changedDuring": None if parent["during"] is None else delta(True),
            "changedSettled": delta(not item["active"])}
