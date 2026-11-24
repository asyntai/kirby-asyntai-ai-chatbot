<?php

use Kirby\Cms\App as Kirby;
use Kirby\Http\Response; 
use Kirby\Toolkit\Dir;
use Kirby\Toolkit\F;

// Helper: absolute path to plugin storage directory
if (!function_exists('asyntai_storage_dir')) {
    function asyntai_storage_dir(): string
    {
        // Use site/storage for broad Kirby compatibility
        $root = kirby()->root('site') . '/storage';
        return $root . '/asyntai';
    }
}

// Helper: read stored configuration (merged with plugin defaults)
if (!function_exists('asyntai_read_config')) {
    function asyntai_read_config(): array
    {
        $defaults = [
            'enabled' => option('asyntai.ai-chatbot.enabled', true),
            'site_id' => option('asyntai.ai-chatbot.siteId', ''),
            'script_url' => option('asyntai.ai-chatbot.scriptUrl', 'https://asyntai.com/static/js/chat-widget.js'),
            'account_email' => option('asyntai.ai-chatbot.accountEmail', ''),
        ];

        $file = asyntai_storage_dir() . '/config.json';
        if (is_file($file)) {
            try {
                $data = json_decode(F::read($file), true) ?: [];
                if (is_array($data)) {
                    $defaults = array_merge($defaults, $data);
                }
            } catch (\Throwable $e) {
                // ignore read errors – fall back to defaults
            }
        }
        return $defaults;
    }
}

// Helper: persist configuration to storage
if (!function_exists('asyntai_write_config')) {
    function asyntai_write_config(array $data): void
    {
        $dir = asyntai_storage_dir();
        if (!is_dir($dir)) {
            Dir::make($dir, true);
        }
        $existing = asyntai_read_config();
        $merged = array_merge($existing, $data);
        F::write($dir . '/config.json', json_encode($merged, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
}

// Helper: settings changes need a signed-in Panel admin and the Panel's CSRF token
if (!function_exists('asyntai_guard_request')) {
    function asyntai_guard_request(): ?Response
    {
        $user = kirby()->user();
        if (!$user || !$user->isAdmin()) {
            return new Response(json_encode(['success' => false, 'error' => 'forbidden']), 'application/json', 403);
        }
        $token = (string)kirby()->request()->header('X-CSRF');
        if ($token === '' || csrf($token) !== true) {
            return new Response(json_encode(['success' => false, 'error' => 'Your session has expired. Reload the page and try again.']), 'application/json', 403);
        }
        return null;
    }
}

// Helper: only the Asyntai widget script may be loaded on the site
if (!function_exists('asyntai_clean_script_url')) {
    function asyntai_clean_script_url(string $url): string
    {
        $url = trim($url);
        if (preg_match('#^https://asyntai\.com/[A-Za-z0-9/._-]+\.js$#', $url)) {
            return $url;
        }
        return 'https://asyntai.com/static/js/chat-widget.js';
    }
}

Kirby::plugin('asyntai/ai-chatbot', [
    'options' => [
        'enabled' => true,
        'scriptUrl' => 'https://asyntai.com/static/js/chat-widget.js',
        'siteId' => '',
        'accountEmail' => '',
    ],

    // Load the Panel JavaScript
    'assets' => [
        'index.js' => __DIR__ . '/index.js',
    ],

    // Inject the widget script into all rendered HTML pages when connected
    'hooks' => [
        'page.render:after' => function (string $html) {
            $cfg = asyntai_read_config();
            $enabled = isset($cfg['enabled']) ? (bool)$cfg['enabled'] : true;
            if (!$enabled) {
                return $html;
            }
            $siteId = isset($cfg['site_id']) ? trim((string)$cfg['site_id']) : '';
            if ($siteId === '') {
                return $html;
            }
            $scriptUrl = asyntai_clean_script_url(isset($cfg['script_url']) ? (string)$cfg['script_url'] : '');

            $injection = '<script type="text/javascript">(function(){var s=document.createElement("script");s.async=true;s.defer=true;s.src=' .
                json_encode($scriptUrl) . ';s.setAttribute("data-asyntai-id",' . json_encode($siteId) . ');s.charset="UTF-8";var f=document.getElementsByTagName("script")[0];if(f&&f.parentNode){f.parentNode.insertBefore(s,f);}else{(document.head||document.documentElement).appendChild(s);}})();</script>';

            if (stripos($html, '</body>') !== false) {
                return str_ireplace('</body>', $injection . '</body>', $html);
            }
            return $html . $injection;
        },
    ],

    // Frontend routes (used by the Panel view) to save/reset connection
    'routes' => [
        [
            'pattern' => 'asyntai-save',
            'method' => 'POST',
            'action'  => function () {
                if ($denied = asyntai_guard_request()) {
                    return $denied;
                }

                $raw = file_get_contents('php://input') ?: '';
                $payload = json_decode($raw, true);
                if (!is_array($payload) || !isset($payload['site_id'])) {
                    $payload = get('site_id') ? $_POST : [];
                }

                $siteId = isset($payload['site_id']) ? trim((string)$payload['site_id']) : '';
                if (!preg_match('/^[A-Za-z0-9_-]{6,64}$/', $siteId)) {
                    return new Response(json_encode(['success' => false, 'error' => 'missing site_id']), 'application/json', 400);
                }

                $data = [
                    'site_id' => $siteId,
                ];
                if (!empty($payload['script_url'])) {
                    $data['script_url'] = asyntai_clean_script_url((string)$payload['script_url']);
                }
                if (!empty($payload['account_email'])) {
                    $data['account_email'] = substr(trim((string)$payload['account_email']), 0, 255);
                }

                asyntai_write_config($data);
                return new Response(json_encode(['success' => true]), 'application/json');
            },
        ],
        [
            'pattern' => 'asyntai-reset',
            'method' => 'POST',
            'action'  => function () {
                if ($denied = asyntai_guard_request()) {
                    return $denied;
                }
                asyntai_write_config([
                    'site_id' => '',
                    'account_email' => '',
                ]);
                return new Response(json_encode(['success' => true]), 'application/json');
            },
        ],
    ],

    // Panel area with a simple view to manage connection
    'areas' => [
        'asyntai' => function ($kirby) {
            return [
                'label' => 'Asyntai AI Chatbot',
                'icon'  => 'chat',
                'menu'  => true,
                'link'  => 'asyntai',
                'views' => [
                    [
                        'pattern' => 'asyntai',
                        'action'  => function () {
                            $cfg = asyntai_read_config();
                            $connected = isset($cfg['site_id']) && trim((string)$cfg['site_id']) !== '';
                            $accountEmail = isset($cfg['account_email']) ? (string)$cfg['account_email'] : '';
                            $expectedOrigin = 'https://asyntai.com';
                            $connectUrl = $expectedOrigin . '/wp-auth?platform=getkirby';

                            $statusColor = $connected ? '#008a20' : '#a00';
                            $statusText  = $connected ? 'Connected' : 'Not connected';

                            $saveUrl = url('asyntai-save');
                            $resetUrl = url('asyntai-reset');

                            return [
                                'component' => 'k-asyntai-view',
                                'title' => 'Asyntai AI Chatbot',
                                'props' => [
                                    'title' => 'Asyntai AI Chatbot',
                                    'connected' => $connected,
                                    'statusColor' => $statusColor,
                                    'statusText' => $statusText,
                                    'accountEmail' => $accountEmail,
                                    'connectUrl' => $connectUrl,
                                    'saveUrl' => $saveUrl,
                                    'resetUrl' => $resetUrl,
                                    'expectedOrigin' => $expectedOrigin,
                                    'csrf' => csrf(),
                                ],
                            ];
                        },
                    ],
                ],
            ];
        },
    ],

    // Provide a snippet to manually render the widget if desired
    'snippets' => [
        'asyntai/widget' => __DIR__ . '/snippets/asyntai/widget.php',
    ],
]);


