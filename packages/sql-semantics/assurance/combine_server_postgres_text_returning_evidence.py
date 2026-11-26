"""Replay typed PostgreSQL mutation projections and physical effects for both releases."""

import run_server_postgres_text_returning
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_text_returning)
