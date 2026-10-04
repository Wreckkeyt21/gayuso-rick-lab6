<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Exposes the Migration library through routes.
 *
 * SECURITY: these routes can drop tables (rollback-all / refresh), so they
 * only run from the CLI (php lava migration ...). To also allow them over
 * HTTP on a *development* machine, set MIGRATION_WEB=true in .env.
 * Never enable that on the deployed (Render) service.
 */
class MigrationController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $web_allowed = filter_var(getenv('MIGRATION_WEB'), FILTER_VALIDATE_BOOLEAN);
        if (!IS_CLI && !$web_allowed) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Migrations are only available from the CLI.', 'status' => 403]);
            exit;
        }

        $this->call->library('migration');
    }

    public function create_migration($migration_class)
    {
        $this->migration->create_migration($migration_class);
    }

    public function migrate()
    {
        $this->migration->migrate();
    }

    public function rollback()
    {
        $this->migration->rollback();
    }

    public function rollback_all()
    {
        $this->migration->rollback_all();
    }

    public function refresh()
    {
        $this->migration->refresh();
    }

    public function status()
    {
        $this->migration->status();
    }
}
