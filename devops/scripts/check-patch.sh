#!/bin/bash
# This script does preliminary checks to make sure that any patches in this repo
# are applied as expected whenever a new commit is made in this repository.

set -euo pipefail

if git apply config/decoupled.patch ; then
    >&2 echo "Patch successful! OK to deploy. After merging PR, push the 'release' branch. See README-internal.md"
else
    >&2 echo "Patch failed. Please review/resolve the errors in the log and/or create a new patch file if necessary."
fi
