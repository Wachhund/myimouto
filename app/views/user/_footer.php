<?php if ($this->contentFor('footer')) : ?>
  <?= $this->contentFor('subnavbar', function () { ?>
    <?= $this->content('footer') ?>
  <?php }) ?>
<?php endif ?>
