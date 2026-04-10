<h3><?=$this->t('.title') ?></h3>

<?= $this->formTag([], function () { ?>
  <p><?=$this->t(['.confirm', 'name' => $this->h($this->pool->pretty_name())]) ?></p>
  <?= $this->submitTag($this->t('buttons._yes')) ?> <?= $this->buttonToFunction($this->t('buttons._no'), "history.back()") ?>
<?php }) ?>

<?= $this->partial("footer") ?>
