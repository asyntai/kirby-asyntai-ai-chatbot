<?php
$cfg = function_exists('asyntai_read_config') ? asyntai_read_config() : [];
$siteId = isset($cfg['site_id']) ? trim((string)$cfg['site_id']) : '';
if ($siteId === '') {
    return;
}
$scriptUrl = isset($cfg['script_url']) && trim((string)$cfg['script_url']) !== ''
    ? trim((string)$cfg['script_url'])
    : 'https://asyntai.com/static/js/chat-widget.js';
?>
<script async defer src="<?= htmlspecialchars($scriptUrl, ENT_QUOTES, 'UTF-8') ?>" data-asyntai-id="<?= htmlspecialchars($siteId, ENT_QUOTES, 'UTF-8') ?>"></script>


