"""Replay both pinned releases of PostgreSQL text declarations."""

import run_server_postgres_text_declarations
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_text_declarations)
