<?php $this->contentFor('subnavbar', function () { ?>
  <li><?= $this->linkTo($this->t('.list'), '#index') ?></li>
  <li><?= $this->linkTo($this->t('.search'), '#search') ?></li>
  <li><?= $this->linkTo($this->t('.history'), '#history') ?></li>
  <li><?= $this->linkTo($this->t('.requests'), ['post#index', 'tags' => 'translation_request']) ?></li>
  <li><?= $this->linkTo($this->t('.help'), 'help#notes') ?></li>
<?php }) ?>
