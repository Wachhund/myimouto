<?= $this->contentFor('subnavbar', function () { ?>
  <li><?= $this->linkTo($this->t('.list'), '#index') ?></li>
  <li><?= $this->linkTo($this->t('.search'), '#search') ?></li>
  <?php if (current_user()->is_janitor_or_higher()) : ?>
    <li><?= $this->linkTo($this->t('.moderate'), '#moderate') ?></li>
  <?php endif ?>
  <li><?= $this->linkTo($this->t('.help'), 'help#comments') ?></li>
<?php }) ?>
