"""Independently replay PostgreSQL materialization and refresh effects."""

import run_server_postgres_materialized_view
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_materialized_view)
