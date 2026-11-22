<?php
/**
 * Page-specific scripts at the end of <body> (the 'pageScripts' section). Global scripts are in head.php.
 * After an htmx navigation these scripts run again for the new page, so they must only work on the page's own
 * elements (no document-level listeners).
 */
?>
    <?= $this->renderSection('pageScripts') ?>
