<?php
declare(strict_types=1);

/**
 * GitHub Webhook Deployment Script
 *
 * This script receives GitHub webhooks and automatically deploys your app.
 *
 * SECURITY: Configuration is loaded from config/.env.deploy
 * Never commit .env.deploy to version control!
 */

// ===== CONFIGURATION =====
$deploy_config = __DIR__ . '/config/.env.deploy';
if (!file_exists($deploy_config)) {
    http_response_code(500);
    die('Deployment configuration not found. Copy config/EXAMPLE.env.deploy to config/.env.deploy');
}
require_once $deploy_config;

// Use constants from config file
$GITHUB_SECRET = defined('GITHUB_WEBHOOK_SECRET') ? GITHUB_WEBHOOK_SECRET : '';
$GITHUB_OWNER  = defined('GITHUB_OWNER') ? GITHUB_OWNER : 'Snoopy-too';
$GITHUB_REPO   = defined('GITHUB_REPO') ? GITHUB_REPO : 'tfdtaskmanager';
$GITHUB_BRANCH = defined('GITHUB_BRANCH') ? GITHUB_BRANCH : 'main';

// ===== END CONFIGURATION =====

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Set up logging
$log_file = __DIR__ . '/deploy.log';

function log_message(string $message): void {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

function verify_github_webhook(string $secret, string $payload, string $signature): bool {
    if (empty($secret) || empty($signature)) {
        return false;
    }
    $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected, $signature);
}

function send_response(int $code, string $message, array $extra = []): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge([
        'status' => ($code === 200 ? 'success' : 'error'),
        'message' => $message
    ], $extra), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit;
}

function get_header(string $name): string {
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    if (!empty($_SERVER[$serverKey])) {
        return (string)$_SERVER[$serverKey];
    }
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $key => $val) {
            if (strcasecmp($key, $name) === 0) {
                return (string)$val;
            }
        }
    }
    return '';
}

function execute_deployment(string $branch): array {
    chdir(__DIR__);
    $allOutput = [];

    // Ensure working tree is clean so git pull never gets blocked by unstaged or untracked changes
    $statusOutput = [];
    $statusReturn = 0;
    exec("git status --porcelain 2>&1", $statusOutput, $statusReturn);
    $dirtyEntries = array_values(array_filter(array_map('trim', $statusOutput)));

    if (!empty($dirtyEntries)) {
        log_message("Notice: Dirty working tree detected before deploy (" . count($dirtyEntries) . " modified/untracked files). Auto-stashing local modifications...");
        $stashOutput = [];
        $stashReturn = 0;
        exec("git stash push -u -m 'Auto-stashed before deploy on " . date('Y-m-d H:i:s') . "' 2>&1", $stashOutput, $stashReturn);
        $allOutput[] = "[Auto-stashed uncommitted changes before deploy]";
        $allOutput = array_merge($allOutput, $stashOutput);
        log_message("Stash result (exit code $stashReturn):\n" . implode("\n", $stashOutput));
    }

    $branchSafe = escapeshellarg($branch);
    $pullOutput = [];
    $pullReturn = 0;
    exec("git pull origin $branchSafe 2>&1", $pullOutput, $pullReturn);
    $allOutput = array_merge($allOutput, $pullOutput);

    return [
        'code' => $pullReturn,
        'output' => $allOutput
    ];
}

try {
    // 1. Support manual deployment via GET parameter ?key=SECRET
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $key = $_GET['key'] ?? '';
        if (empty($key) || !hash_equals($GITHUB_SECRET, (string)$key)) {
            send_response(403, 'Forbidden: Invalid deploy key.');
        }

        log_message('Manual deployment triggered via GET ?key');
        $deployResult = execute_deployment($GITHUB_BRANCH);
        $output = $deployResult['output'];
        $returnVar = $deployResult['code'];

        if ($returnVar === 0) {
            log_message("SUCCESS: Git pull executed successfully.\n" . implode("\n", $output));
            send_response(200, 'Deployment successful', ['output' => $output]);
        } else {
            log_message("FAILURE: Git pull failed with exit code $returnVar.\n" . implode("\n", $output));
            send_response(500, "Git pull failed with exit code $returnVar", ['output' => $output]);
        }
    }

    // 2. Only allow POST requests for webhooks
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_response(405, 'Method Not Allowed. Expected POST webhook.');
    }

    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] Webhook script executed\n", FILE_APPEND);
    log_message('Webhook received from GitHub');

    // Get the raw POST data
    $payload = file_get_contents('php://input');
    log_message('Payload size: ' . strlen($payload) . ' bytes');

    if ($payload === false || $payload === '') {
        log_message('Error: No payload received');
        send_response(400, 'No payload received');
    }

    // Verify webhook signature
    $signature = get_header('X-Hub-Signature-256');
    log_message('Signature received: ' . (strlen($signature) > 20 ? substr($signature, 0, 20) . '...' : ($signature ?: 'none')));

    if (!verify_github_webhook($GITHUB_SECRET, $payload, $signature)) {
        log_message('Invalid webhook signature - verification failed');
        send_response(403, 'Invalid signature');
    }

    log_message('Webhook signature verified successfully');

    // Handle GitHub Ping event
    $githubEvent = get_header('X-GitHub-Event');
    if (empty($githubEvent)) {
        $githubEvent = 'push';
    }

    if ($githubEvent === 'ping') {
        log_message('Ping event received from GitHub. Webhook connection verified successfully.');
        send_response(200, 'GitHub webhook ping received successfully');
    }

    // Parse the JSON payload
    $data = json_decode($payload, true);
    if (!is_array($data)) {
        log_message('Error: Invalid JSON payload');
        send_response(400, 'Invalid JSON payload');
    }

    // Check if this is a push event on the target branch
    $targetRef = "refs/heads/$GITHUB_BRANCH";
    $receivedRef = $data['ref'] ?? '';
    if ($receivedRef !== $targetRef) {
        log_message("Push to different branch received: '$receivedRef', ignoring (expected '$targetRef')");
        send_response(200, "Not target branch ($GITHUB_BRANCH), ignoring");
    }

    log_message("Valid webhook received for $GITHUB_BRANCH branch");

    // Extract commit details for logging
    $commit = $data['head_commit'] ?? [];
    $author = $commit['author']['name'] ?? ($data['pusher']['name'] ?? 'Unknown');
    $message = $commit['message'] ?? 'No commit message';
    $commitId = substr($commit['id'] ?? ($data['after'] ?? 'unknown'), 0, 7);

    log_message("Deployment triggered by: $author - [$commitId] $message");

    // Execute git pull with auto-stash resilience
    $deployResult = execute_deployment($GITHUB_BRANCH);
    $output = $deployResult['output'];
    $returnVar = $deployResult['code'];

    if ($returnVar === 0) {
        log_message("Deployment completed successfully: \n" . implode("\n", $output));
        send_response(200, 'Deployment successful', [
            'branch' => $GITHUB_BRANCH,
            'commit' => $commitId,
            'author' => $author,
            'output' => $output
        ]);
    } else {
        log_message("Deployment failed (exit code $returnVar): \n" . implode("\n", $output));
        send_response(500, "Deployment failed: Git pull exited with code $returnVar", [
            'output' => $output
        ]);
    }

} catch (Throwable $e) {
    log_message('Exception: ' . $e->getMessage());
    send_response(500, 'Deployment error: ' . $e->getMessage());
}
