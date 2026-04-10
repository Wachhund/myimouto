<?= $this->formTag([], ['onsubmit' => "return confirm('{$this->t('tag_js')}')"], function () { ?>
  <?= $this->textFieldTag("start", $this->params()->source, ['size' => 60]) ?>
  <?= $this->textFieldTag("result", $this->params()->name, ['size' => 60]) ?>
  <?= $this->buttonToFunction($this->t('tag_js_preview'), "$('preview').innerHTML = '{$this->t('tag_js_preview_txt')}'; new Ajax.Updater('preview', '/tag/edit_preview', {method: 'get', parameters: 'tags=' + \$F('start')})") ?><?= $this->submitTag($this->t('tag_save')) ?>
<?php }) ?>

<?= $this->partial("footer") ?>

<div id="preview">
</div>
