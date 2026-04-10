<?php $this->contentFor('subnavbar', function () { ?>
  <li><?= $this->linkTo($this->t('.list'), ['action' => "index"]) ?></li>
  <?= $this->content('footer') ?>
  <!-- <li><?= $this->linkTo($this->t('.help'), ['controller' => "help", 'action' => "inlines"]) ?></li> -->
<?php }) ?>
