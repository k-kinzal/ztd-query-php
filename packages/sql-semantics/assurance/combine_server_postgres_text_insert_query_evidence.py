"""Replay typed PostgreSQL SELECT-to-INSERT effects for both native releases."""

import run_server_postgres_text_insert_query
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_text_insert_query)
