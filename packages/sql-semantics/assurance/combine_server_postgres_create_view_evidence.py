"""Independently replay PostgreSQL persistent view catalog and write effects."""

import run_server_postgres_create_view
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_create_view)
