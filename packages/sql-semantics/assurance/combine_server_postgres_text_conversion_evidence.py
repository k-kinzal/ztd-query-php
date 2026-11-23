"""Replay PostgreSQL text conversion and binding evidence for both pinned releases."""

import run_server_postgres_text_conversion
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_text_conversion)
