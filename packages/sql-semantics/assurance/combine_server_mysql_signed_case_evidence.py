"""Independently replay the fixed native MySQL signed CASE evidence."""

from combine_server_join_evidence import main
import run_server_mysql_signed_case


if __name__ == "__main__":
    main(run_server_mysql_signed_case)
