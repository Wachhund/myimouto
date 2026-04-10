<div id="tag-list">
  <h1><?= $this->linkTo('«', ['controller' => "tag", 'action' => "popular_by_week", 'year' => date('Y', strtotime('-1 week', $this->start)), 'month' => date('m', strtotime('-1 week', $this->start)), 'day' => date('d', strtotime('-1 week', $this->start))]) ?> <?= date("F d, Y", $this->start) ?> - <?= date("F d, Y", $this->end) ?> <?= $this->linkToIf($this->start <= time(), '»', ['controller' => "tag", 'action' => "popular_by_week", 'year' => date('Y', strtotime('+1 week', $this->start)), 'month' => date('m', strtotime('+1 week', $this->start)), 'day' => date('d', strtotime('+1 week', $this->start))]) ?></h1>

  <?= $this->cloud_view($this->tags, 3) ?>
</div>

<?php $this->contentFor('footer', function () { ?>
  <p><?= $this->linkTo($this->t('tag_popular_day'), ['action' => "popular_by_day"]) ?> | <?= $this->linkTo($this->t('tag_popular_week'), ['action' => "popular_by_week"]) ?> | <?= $this->linkTo($this->t('tag_popular_month'), ['action' => "popular_by_month"]) ?></p>
<?php }) ?>

<?= $this->partial("footer") ?>
