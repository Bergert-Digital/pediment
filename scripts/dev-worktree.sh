#!/bin/sh
# Start the local WordPress (wp-env) for this checkout on a port that is fixed
# per checkout, then run the plugin's block watcher in the foreground.
# Used by the T3 Code "Dev" project action; works in any worktree.
#
# Ports: a block of two, PORT (the site) and PORT+1 (the wp-env tests site).
# PORT = BASE + (cksum of the checkout's real path) % SPAN, so a restart in the
# same worktree gets the same port and parallel worktrees get different ones.
# Set PORT=<n> to override. If another process holds a port of the block, the
# next free block is used and a warning is printed. This checkout's own wp-env
# containers do not count as "another process", so a restart keeps its port.
#
# The ports are written into the gitignored .wp-env.override.json (other keys,
# such as a live AI key under env.development, are kept), so `npm run
# env:start` and `npx wp-env run …` agree on them. For e2e, set
# PLAYWRIGHT_BASE_URL to the printed URL. wp-env sees a changed port and
# reconfigures WordPress (WP_HOME/WP_SITEURL). Stored absolute URLs (seeded
# navigation links, options) still carry the port of whatever started the
# database first (this script, Conductor's run, a manual start), so every start
# rewrites each http://localhost:<port> in the database to the current URL.
#
# `npm run env:start` boots the site and runs tools/dev-bootstrap.mjs
# (activate fixture theme + plugin, languages, seed; idempotent). Then the
# block watcher (`npm start`) runs until you stop it.
# - Stop (Ctrl-C, SIGTERM, SIGHUP): stops the watcher and this checkout's wp-env.
# - Starting the script again while it runs: the old watcher stops, wp-env stays up.

set -e

BASE=9800   # start of this project's port range (wp-env's default is 8888/8889)
SPAN=900    # size of the range: 9800-10699
BLOCK=2     # ports per checkout: site + tests site
SCHEME=http

root=$(git rev-parse --show-toplevel)
cd "$root"
here=$(pwd -P)
pidfile="$(git rev-parse --absolute-git-dir)/dev-worktree.pid"

# A fresh worktree has no dependencies yet. `npm ci`, never `npm install`:
# npm 11 rewrites the lockfiles into a form CI's npm 10 rejects.
[ -d node_modules ] || npm ci
[ -d plugin/vendor ] || composer install -d plugin
[ -d plugin/node_modules ] || (cd plugin && npm ci)
[ -d plugin/build ] || npm run build

# Stop the watcher of an earlier run of this checkout. USR1 tells that run to
# leave wp-env up, because this run takes it over.
if [ -f "$pidfile" ]; then
	old=$(cat "$pidfile")
	if [ -n "$old" ] && [ "$old" != "$$" ] && kill -0 "$old" 2>/dev/null; then
		echo "dev-worktree: stopping the earlier run of this checkout (pid $old)" >&2
		kill -USR1 "$old" 2>/dev/null || true
		n=0
		while kill -0 "$old" 2>/dev/null && [ "$n" -lt 50 ]; do
			sleep 0.1
			n=$((n + 1))
		done
	fi
fi
echo $$ >"$pidfile"

# True when a process other than this checkout's own wp-env listens on port $1.
# A container is ours when it mounts a directory of this checkout (wp-env maps
# ./plugin and the fixture theme). Docker Desktop holds published ports in its
# own backend process, so the listener's cwd cannot tell.
port_in_use() {
	lsof -nP -iTCP:"$1" -sTCP:LISTEN >/dev/null 2>&1 || return 1
	ids=$(docker ps -q --filter "publish=$1" 2>/dev/null)
	[ -n "$ids" ] || return 0
	# shellcheck disable=SC2086
	mounts=$(docker inspect --format '{{range .Mounts}}{{.Source}}{{"\n"}}{{end}}' $ids 2>/dev/null)
	case "$mounts" in
	*"$here/"*) return 1 ;;
	esac
	return 0
}

block_in_use() {
	i=0
	while [ "$i" -lt "$BLOCK" ]; do
		port_in_use $(($1 + i)) && return 0
		i=$((i + 1))
	done
	return 1
}

if [ -n "$PORT" ]; then
	port=$PORT
else
	hash=$(printf %s "$here" | cksum | cut -d' ' -f1)
	port=$((BASE + (hash % (SPAN / BLOCK)) * BLOCK))
	preferred=$port
	while block_in_use "$port"; do
		port=$((port + BLOCK))
	done
	if [ "$port" != "$preferred" ]; then
		echo "dev-worktree: port $preferred or $((preferred + 1)) is taken by another process, using $port" >&2
	fi
fi
tests_port=$((port + 1))

# Write the ports into .wp-env.override.json and keep all other keys.
node -e '
const fs = require("fs");
const [file, port, testsPort] = process.argv.slice(1);
const data = fs.existsSync(file) ? JSON.parse(fs.readFileSync(file, "utf8")) : {};
data.port = Number(port);
data.testsPort = Number(testsPort);
fs.writeFileSync(file, JSON.stringify(data, null, 2) + "\n");
' .wp-env.override.json "$port" "$tests_port"

url="$SCHEME://localhost:$port"
echo "dev-worktree: $here → $url (tests site: $SCHEME://localhost:$tests_port)"

# WP_ENV_PORT / WP_ENV_TESTS_PORT from the shell would win over the override
# file; drop them so the file is the single source.
unset WP_ENV_PORT WP_ENV_TESTS_PORT
npm run env:start

# Move stored URLs of any earlier port to this one (about 2 s; a no-op when
# nothing is stale).
npx wp-env run cli wp search-replace "$SCHEME://localhost:[0-9]+" "$url" \
	--regex --all-tables --skip-columns=guid --report-changed-only
echo "dev-worktree: WordPress is up at $url (admin: $url/wp-admin, admin/password)"

# Run the watcher in its own process group, so one kill stops npm and webpack.
set -m
npm start &
watcher=$!
set +m

stop_watcher() {
	kill -TERM "-$watcher" 2>/dev/null || true
	wait "$watcher" 2>/dev/null || true
	[ "$(cat "$pidfile" 2>/dev/null)" = "$$" ] && rm -f "$pidfile"
}

trap 'trap - INT TERM HUP USR1; stop_watcher; echo "dev-worktree: replaced by a new run, wp-env stays up" >&2; exit 0' USR1
trap 'trap - INT TERM HUP USR1; stop_watcher; echo "dev-worktree: stopping wp-env" >&2; npm run env:stop; exit 0' INT TERM HUP

wait "$watcher"
