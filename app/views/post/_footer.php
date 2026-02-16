<?= $this->contentFor('subnavbar', function() { ?>
  <li><?= $this->linkTo($this->t('.list'), 'post#index') ?></li>
  <li><?= $this->linkTo($this->t('.browse'), $this->urlFor(['post#browse', 'anchor' => '/']) . str_replace('+', ' ', $this->h($this->params()->tags))) ?></li>
  <li><?= $this->linkTo($this->t('.upload'), 'post#upload') ?></li>
  <!-- <li id="my-subscriptions-container"><?php //echo $this->linkTo($this->t('.subs'), "/", 'id' => 'my-subscriptions') ?></li> -->
  <li><?= $this->linkTo($this->t('.random'), array('post#', 'tags' => 'order:random')) ?></li>
  <li><?= $this->linkTo($this->t('.popular'), 'post#popular_recent') ?></li>
  <li><?= $this->linkTo($this->t('.image_search'), 'post#similar') ?></li>
  <li><?= $this->linkTo($this->t('.history'), 'history#index') ?></li>
  <?php if (current_user()->is_contributor_or_higher()) : ?>
    <li><?= $this->linkTo($this->t('.batch'), 'batch#') ?></li>
  <?php endif ?>
  <?php if (current_user()->is_janitor_or_higher()) : ?>
    <li><?= $this->linkTo($this->t('.mod'), 'post#moderate', array('id' => 'moderate')) ?></li>
  <?php endif ?>
  <?= $this->content('footer') ?>
  <li><?= $this->linkTo($this->t('.help'), 'help#posts') ?></li>
<?php }) ?>
