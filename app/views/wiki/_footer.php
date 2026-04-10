<?php $this->contentFor('subnavbar', function () { ?>
  <li><?= $this->linkTo($this->t('.list'), ['action' => "index"]) ?></li>
  <li><?= $this->linkTo($this->t('.new'), ['action' => "add"]) ?></li>
  <?= $this->content('footer') ?>
  <li><?= $this->linkTo($this->t('.help'), ['controller' => "help", 'action' => "wiki"]) ?></li>
<?php }) ?>
