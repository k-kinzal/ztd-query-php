"""The registered native domains and preserved artifact layouts."""


NATIVE = {
    "projection": "server-projection-v1.json",
    "postgres_int4": "postgres-int4-operators-v2.json",
    "postgres_merged_join": "postgres-merged-join-v2.json",
    "postgres_full_merged_join": "postgres-full-merged-join-v1.json",
    "postgres_from_list": "postgres-from-list-v2.json",
    "postgres_boolean_tests": "postgres-boolean-tests-v1.json",
    "postgres_builtin_casts": "postgres-builtin-casts-v3.json",
    "postgres_typed_parameters": "postgres-typed-parameters-v1.json",
    "postgres_int4_distinctness": "postgres-int4-distinctness-v1.json",
    "postgres_int4_range": "postgres-int4-range-v1.json",
    "postgres_int4_case": "postgres-int4-case-v1.json",
    "postgres_delete_returning": "postgres-delete-returning-v1.json",
    "postgres_update_returning": "postgres-update-returning-v2.json",
    "postgres_insert_returning": "postgres-insert-returning-v1.json",
    "mysql_signed_range": "mysql-signed-range-v3.json",
    "mysql_signed_quotient": "mysql-signed-quotient-v1.json",
    "mysql_signed_casts": "mysql-signed-casts-v2.json",
    "mysql_unsigned_casts": "mysql-unsigned-casts-v3.json",
    "mysql_merged_join": "mysql-merged-join-v1.json",
    "mysql_from_list": "mysql-from-list-v1.json",
    "delete": "server-delete-v2.json",
    "join": "server-join-v2.json",
    "result": "server-result-v1.json",
    "insert": "server-insert-v1.json",
    "update": "server-update-v2.json",
    "transaction": "server-transaction-v1.json",
    "drop_table": "server-drop-table-v1.json",
    "create_table": "server-create-table-v1.json",
    "parameter": "server-parameter-v5.json",
    "compound": "server-compound-v2.json",
    "local_projection": "server-local-projection-v4.json",
    "truth": "server-truth-v2.json",
    "recursive_series": "server-recursive-series-v2.json",
    "derived_join": "server-derived-join-v2.json",
    "grouped": "server-grouped-v3.json",
    "insert_query": "server-insert-query-v2.json",
    "wildcard": "wildcard-projection-v2.json",
}


SINGLE_ENGINE = {
    "postgres_int4": "postgres",
    "postgres_merged_join": "postgres",
    "postgres_full_merged_join": "postgres",
    "postgres_from_list": "postgres",
    "postgres_int4_case": "postgres",
    "postgres_boolean_tests": "postgres",
    "postgres_builtin_casts": "postgres",
    "postgres_typed_parameters": "postgres",
    "postgres_int4_distinctness": "postgres",
    "postgres_int4_range": "postgres",
    "postgres_delete_returning": "postgres",
    "postgres_update_returning": "postgres",
    "postgres_insert_returning": "postgres",
    "mysql_signed_range": "mysql",
    "mysql_signed_quotient": "mysql",
    "mysql_signed_casts": "mysql",
    "mysql_unsigned_casts": "mysql",
    "mysql_merged_join": "mysql",
    "mysql_from_list": "mysql",
}


def reports(raw, layout, domain):
    if layout == "release":
        for engine in ("mysql", "postgres"):
            yield from sorted(raw.glob(f"{engine}-*/{domain}/index.json"))
        return
    prefix = "semantics-ir" if domain == "projection" else f"native-{domain.replace('_', '-')}"
    for engine in ("mysql", "postgres"):
        yield from sorted(raw.glob(f"{prefix}-{engine}-*/index.json"))
