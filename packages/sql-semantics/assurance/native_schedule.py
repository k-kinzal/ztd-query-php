"""The registered native domains and preserved artifact layouts."""


NATIVE = {
    "projection": "server-projection-v1.json",
    "postgres_int4": "postgres-int4-operators-v1.json",
    "postgres_int4_case": "postgres-int4-case-v1.json",
    "postgres_delete_returning": "postgres-delete-returning-v1.json",
    "mysql_signed_quotient": "mysql-signed-quotient-v1.json",
    "delete": "server-delete-v2.json",
    "join": "server-join-v1.json",
    "result": "server-result-v1.json",
    "insert": "server-insert-v1.json",
    "update": "server-update-v2.json",
    "transaction": "server-transaction-v1.json",
    "drop_table": "server-drop-table-v1.json",
    "create_table": "server-create-table-v1.json",
    "parameter": "server-parameter-v1.json",
    "compound": "server-compound-v2.json",
    "local_projection": "server-local-projection-v4.json",
    "truth": "server-truth-v2.json",
    "recursive_series": "server-recursive-series-v2.json",
    "derived_join": "server-derived-join-v2.json",
    "grouped": "server-grouped-v3.json",
    "insert_query": "server-insert-query-v2.json",
    "wildcard": "wildcard-projection-v2.json",
}


def reports(raw, layout, domain):
    if layout == "release":
        for engine in ("mysql", "postgres"):
            yield from sorted(raw.glob(f"{engine}-*/{domain}/index.json"))
        return
    prefix = "semantics-ir" if domain == "projection" else f"native-{domain.replace('_', '-')}"
    for engine in ("mysql", "postgres"):
        yield from sorted(raw.glob(f"{prefix}-{engine}-*/index.json"))
