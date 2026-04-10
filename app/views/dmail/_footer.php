<?php $this->contentFor('subnavbar', function () { ?>
  <?= $this->content('footer') ?>
  <li><?= $this->linkTo($this->t('.inbox'), ['action' => "inbox"]) ?></li>
  <li><?= $this->linkTo($this->t('.compose'), ['action' => "compose"]) ?></li>
  <li><?= $this->linkTo($this->t('.mark_all_read'), ['action' => "mark_all_read"], ['method' => 'post']) ?></li>
<?php }) ?>
