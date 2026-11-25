"""Independently replay evidence for persistent MySQL view headings."""

from combine_server_join_evidence import main
import run_server_mysql_create_view_output_names


if __name__ == "__main__":
    main(run_server_mysql_create_view_output_names)
