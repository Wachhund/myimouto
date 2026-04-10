<div id="tag-list">
  <h1><?= $this->linkTo('«', ['controller' => "tag", 'action' => "popular_by_month", 'year' => date('Y', strtotime('-1 month', $this->start)), 'month' => date('m', strtotime('-1 month', $this->start))]) ?> <?= date("F Y", $this->start) ?> <?= $this->linkToIf($this->start <= time(), '»', ['controller' => "tag", 'action' => "popular_by_month", 'year' => date('Y', $this->end), 'month' => date('m', $this->end)]) ?></h1>

  <?= $this->cloud_view($this->tags, 4) ?>
</div>

<?php $this->contentFor('footer', function () { ?>
  <p><?= $this->linkTo($this->t('tag_popular_day'), ['action' => "popular_by_day"]) ?> | <?= $this->linkTo($this->t('tag_popular_week'), ['action' => "popular_by_week"]) ?> | <?= $this->linkTo($this->t('tag_popular_month'), ['action' => "popular_by_month"]) ?></p>
<?php }) ?>

<?= $this->partial("footer") ?>
