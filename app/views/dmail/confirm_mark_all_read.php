<h4><?= $this->t('.title') ?></h4>
<p><?= $this->t('.info') ?></p>

<?= $this->formTag("#mark_all_read", function () { ?>
  <?= $this->submitTag($this->t('buttons._yes')) ?>
  <?= $this->submitTag($this->t('buttons._no')) ?>
<?php }) ?>
