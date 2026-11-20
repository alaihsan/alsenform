<?php

/*
 * Laravel Boost, the MCP server of the AI tools (Codex, OpenCode), is off while APP_ENV=production,
 * as on the school's server, where "php artisan boost:mcp" ended in "There are no commands defined
 * in the "boost" namespace". The tools start it with --env=local: on in their own process only,
 * never on the pages the server serves.
 */

test('Codex starts Boost with --env=local', function () {
    expect(file_get_contents(base_path('.codex/config.toml')))
        ->toContain('args = ["artisan", "boost:mcp", "--env=local"]');
});

test('OpenCode starts Boost with --env=local', function () {
    $config = json_decode((string) file_get_contents(base_path('opencode.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($config['mcp']['laravel-boost']['command'])->toBe(['php', 'artisan', 'boost:mcp', '--env=local']);
});
