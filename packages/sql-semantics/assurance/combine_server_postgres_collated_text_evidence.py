"""Re-evaluate the native text collation evidence on a separate verifier run."""

import run_server_postgres_collated_text
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_collated_text)
