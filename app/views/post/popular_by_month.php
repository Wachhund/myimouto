<div id="post-popular">
  <h3>
    <?= $this->linkTo('«', ['post#popular_by_month', 'year' => date('Y', strtotime('-1 month', $this->start)), 'month' => date('m', strtotime('-1 month', $this->start))]) ?>
    <?= date("F Y", $this->start) ?>
    <?= $this->linkToIf($this->start <= time(), '»', ['post#popular_by_month', 'year' => date('Y', $this->end), 'month' => date('m', $this->end)]) ?>
  </h3>

  <?= $this->partial('posts', ['posts' => $this->posts]) ?>
</div>

<?= $this->contentFor('subnavbar', function () { ?>
  <li><?= $this->linkTo("Popular", ['post#popular_by_day', 'month' => date('m', $this->start), 'day' => date('d', $this->start), 'year' => date('Y', $this->start)]) ?></li>
  <li><?= $this->linkTo("Popular (by week)", ['post#popular_by_week', 'year' => date('Y', $this->start), 'month' => date('m', $this->start), 'day' => date('d', $this->start)]) ?></li>
  <li><?= $this->linkTo("Popular (by month)", ['post#popular_by_month', 'year' => date('Y', $this->start), 'month' => date('m', $this->start)]) ?></li>
<?php }) ?>

<?= $this->partial('footer') ?>
