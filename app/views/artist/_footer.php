<?php $this->contentFor('subnavbar', function () { ?>
  <li><?= $this->linkTo($this->t('.list'), ['action' => 'index']) ?></li>
  <li><?= $this->linkTo($this->t('.add'), ['action' => 'create']) ?></li>
  <li><?= $this->linkTo($this->t('.help'), ['controller' => '/help', 'action' => 'artists']) ?></li>
<?php }) ?>
