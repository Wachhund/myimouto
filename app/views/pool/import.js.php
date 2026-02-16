<?php ob_start(); ?>
<?= $this->partial('import_list') ?>
<?php $import_list_html = ob_get_clean(); ?>
Element.update('posts', '<?= $this->escapeJavascript($import_list_html) ?>');
