<?php $this->contentFor('subnavbar', function () { ?>
  <li><?= $this->linkTo($this->t('.list'), ['action' => "index"]) ?></li>
  <li><?= $this->linkTo($this->t('.new'), ['action' => "create"]) ?></li>
  <?= $this->content('footer') ?>
  <li><?= $this->linkTo($this->t('.help'), ['controller' => "help", 'action' => "pools"]) ?></li>
  <?= $this->content('footer_final') ?>
<?php }) ?>
