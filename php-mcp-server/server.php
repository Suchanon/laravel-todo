<?php

declare(strict_types=1);

require_once __DIR__.'/vendor/autoload.php';

use Mcp\Server;
use Mcp\Server\Transport\StdioTransport;

/**
 * Creates and configures the PHP MCP server.
 */
$server = Server::builder()
    ->setServerInfo('my-php-mcp-server', '1.0.0', 'Official PHP MCP Server implementation')
    ->addTool(
        handler: function (string $filePath): string {
            if (! file_exists($filePath)) {
                return 'Error: File not found at '.$filePath;
            }

            $content = file_get_contents($filePath);
            $lines = explode("\n", $content);
            $issues = [];

            // 1. Check strict types
            if (! str_contains($content, 'declare(strict_types=1);')) {
                $issues[] = [
                    'type' => 'Style / Type Safety',
                    'line' => 1,
                    'message' => 'Missing "declare(strict_types=1);" statement at the top of the file.',
                    'suggestion' => 'Add "declare(strict_types=1);" right after the opening <?php tag.',
                ];
            }

            // 2. Scan methods using token_get_all
            $tokens = token_get_all($content);
            $className = '';
            $methods = [];
            $currentMethod = null;

            foreach ($tokens as $index => $token) {
                if (is_array($token)) {
                    if ($token[0] === T_CLASS) {
                        // Find class name
                        for ($j = $index + 1; $j < $index + 10; $j++) {
                            if (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                                $className = $tokens[$j][1];
                                break;
                            }
                        }
                    }
                    if ($token[0] === T_FUNCTION) {
                        // Find function name
                        for ($j = $index + 1; $j < $index + 10; $j++) {
                            if (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                                $currentMethod = $tokens[$j][1];
                                $methods[$currentMethod] = [
                                    'name' => $currentMethod,
                                    'line' => $token[2],
                                    'params' => [],
                                    'return_type' => null,
                                ];
                                break;
                            }
                        }
                    }
                }
            }

            // 3. Find unused methods in the codebase (simple grep across the project)
            $projectDir = dirname(__DIR__); // Assuming app is sibling to php-mcp-server
            if ($className) {
                foreach ($methods as $methodName => $meta) {
                    $searchPattern = '->'.$methodName.'(';
                    $isUsed = false;

                    // Scan app/ directory
                    $appDir = $projectDir.'/app';
                    if (is_dir($appDir)) {
                        $dirIter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appDir));
                        foreach ($dirIter as $file) {
                            if ($file->isFile() && $file->getExtension() === 'php') {
                                // Skip the service file itself
                                if (realpath($file->getPathname()) === realpath($filePath)) {
                                    continue;
                                }
                                $fileContent = file_get_contents($file->getPathname());
                                if (str_contains($fileContent, $searchPattern)) {
                                    $isUsed = true;
                                    break;
                                }
                            }
                        }
                    }

                    // Scan tests/ directory
                    $testsDir = $projectDir.'/tests';
                    if (! $isUsed && is_dir($testsDir)) {
                        $dirIter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testsDir));
                        foreach ($dirIter as $file) {
                            if ($file->isFile() && $file->getExtension() === 'php') {
                                $fileContent = file_get_contents($file->getPathname());
                                if (str_contains($fileContent, $searchPattern)) {
                                    $isUsed = true;
                                    break;
                                }
                            }
                        }
                    }

                    if (! $isUsed) {
                        $issues[] = [
                            'type' => 'Dead Code',
                            'line' => $meta['line'],
                            'message' => "Method '{$methodName}' is defined in {$className} but never called in controllers or tests.",
                            'suggestion' => "Consider removing '{$methodName}' if it is unused, or ensure all usages are implemented.",
                        ];
                    }
                }
            }

            // 4. Line-by-line checks
            foreach ($lines as $i => $line) {
                $lineNum = $i + 1;

                // Check for formatting issue: "void{" or ") {" space check
                if (preg_match('/:\s*void\{/', $line)) {
                    $issues[] = [
                        'type' => 'PSR-12 Formatting',
                        'line' => $lineNum,
                        'message' => 'Missing space before opening curly brace after return type void.',
                        'suggestion' => 'Change "void{" to "void {" to follow PSR-12 and Pint rules.',
                    ];
                }

                // Check for Eloquent ::all() or ::query()->... without user/owner scoping
                if (preg_match('/Task::(query\(\)->)?(latest\(\)->)?get\(\)/', $line)) {
                    $issues[] = [
                        'type' => 'Eloquent Scoping & Security',
                        'line' => $lineNum,
                        'message' => 'Querying all tasks without user-scoping or pagination.',
                        'suggestion' => 'Pass a User model or scope to only fetch the authenticated user\'s tasks (e.g. $user->tasks()->latest()->get() or Task::where(\'user_id\', $user->id)->get()).',
                    ];
                }

                // Check for ::create() in service directly
                if (preg_match('/Task::create\(/', $line)) {
                    $issues[] = [
                        'type' => 'Eloquent & Security',
                        'line' => $lineNum,
                        'message' => 'Creating a task directly without binding it to the user.',
                        'suggestion' => 'Ensure the task is created via the User relationship ($user->tasks()->create($data)) or that user_id is explicitly set and validated in the payload.',
                    ];
                }

                // Check for toggle complete boolean logic
                if (str_contains($line, '\'is_completed\' => ! $task->is_completed')) {
                    $issues[] = [
                        'type' => 'Refactoring',
                        'line' => $lineNum,
                        'message' => 'Toggling boolean state using inline update query.',
                        'suggestion' => 'Consider using a model method or explicit toggling like `$task->toggleComplete()->save();` for better domain modeling.',
                    ];
                }
            }

            // Prepare response formatting
            $output = '### PHP Code Analysis for '.basename($filePath)."\n\n";
            if (empty($issues)) {
                $output .= "✅ No issues found! The class looks clean and follows all rules.\n";
            } else {
                $output .= "| Line | Type | Message | Suggestion |\n";
                $output .= "|---|---|---|---|\n";
                foreach ($issues as $issue) {
                    $output .= '| '.$issue['line'].' | '.$issue['type'].' | '.$issue['message'].' | '.$issue['suggestion']." |\n";
                }
            }

            return $output;
        },
        name: 'analyze_php_file',
        description: 'Analyzes a PHP class file for style, dead code, security, and Eloquent practices.'
    )
    ->build();

$transport = new StdioTransport;
$server->run($transport);
