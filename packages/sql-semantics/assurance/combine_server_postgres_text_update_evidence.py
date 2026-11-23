"""Replay PostgreSQL comparisons of typed text UPDATE effects."""

import run_server_postgres_text_update
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_text_update)
