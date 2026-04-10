<div id="post-popular">
  <h3><?= $this->linkTo('«', ['post#popular_by_day', 'year' => date('Y', strtotime('-1 day', $this->day)), 'month' => date('m', strtotime('-1 day', $this->day)), 'day' => date('d', strtotime('-1 day', $this->day))]) ?> <?= date("F d, Y", $this->day) ?> <?= $this->linkToIf($this->day <= time(), '»', ['post#popular_by_day', 'year' => date('Y', strtotime('+1 day', $this->day)), 'month' => date('m', strtotime('+1 day', $this->day)), 'day' => date('d', strtotime('+1 day', $this->day))]) ?></h3>

  <?= $this->partial('posts', ['posts' => $this->posts]) ?>
</div>

<?= $this->contentFor('subnavbar', function () { ?>
  <li><?= $this->linkTo("Popular", ['post#popular_by_day', 'month' => date('m', $this->day), 'day' => date('d', $this->day), 'year' => date('Y', $this->day)]) ?></li>
  <li><?= $this->linkTo("Popular (by week)", ['post#popular_by_week', 'year' => date('Y', $this->day), 'month' => date('m', $this->day), 'day' => date('d', $this->day)]) ?></li>
  <li><?= $this->linkTo("Popular (by month)", ['post#popular_by_month', 'year' => date('Y', $this->day), 'month' => date('m', $this->day)]) ?></li>
<?php }) ?>

<?= $this->partial('footer') ?>
