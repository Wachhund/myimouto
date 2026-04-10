<div id="tag-list">
  <h1><?= $this->linkTo('«', ['controller' => "tag", 'action' => "popular_by_day", 'year' => date('Y', strtotime('-1 day', $this->day)), 'month' => date('m', strtotime('-1 day', $this->day)), 'day' => date('d', strtotime('-1 day', $this->day))]) ?> <?= date("F d, Y", $this->day) ?> <?= $this->linkToIf($this->day <= time(), '»', ['tag#popular_by_day', 'year' => date('Y', strtotime('+1 day', $this->day)), 'month' => date('m', strtotime('+1 day', $this->day)), 'day' => date('d', strtotime('+1 day', $this->day))]) ?></h1>
  <?= $this->cloud_view($this->tags, 1.5) ?>
</div>

<?php $this->contentFor('footer', function () { ?>
  <li><?= $this->linkTo($this->t('static7'), ['action' => "popular_by_day"]) ?></li>
  <li><?= $this->linkTo($this->t('static8'), ['action' => "popular_by_week"]) ?></li>
  <li><?= $this->linkTo($this->t('static9'), ['action' => "popular_by_month"]) ?></li>
<?php }) ?>

<?= $this->partial("footer") ?>
