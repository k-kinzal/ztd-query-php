"""Independently replay PostgreSQL nullable index predicate observations."""

from combine_server_join_evidence import main
import run_server_postgres_index_boolean_values

if __name__ == "__main__":
    main(run_server_postgres_index_boolean_values)
