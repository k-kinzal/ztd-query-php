"""Independently replay evidence of generated persistent MySQL view names."""

from combine_server_join_evidence import main
import run_server_mysql_generated_view_headings


if __name__ == "__main__":
    main(run_server_mysql_generated_view_headings)
