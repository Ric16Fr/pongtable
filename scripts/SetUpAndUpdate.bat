call bun install
call bun update
call bun upgrade
call install
call composer update
call composer upgrade
call composer self-update
call bun run build
echo Set Up completed. Installing Chrome for Tests now...
echo "Try to Update Dusk Driver and Chrome"
bunx playwright install
echo "All completed"
