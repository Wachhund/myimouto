<div id="static-more">
  <h2><?= $this->linkTo(CONFIG()->app_name, '/', ['id' => "site-link"]) ?></h2>
  <h5><?= $this->t('.reason') ?>: <?= $this->h($this->ban->reason) ?></h5>

  <?php if ($this->ban->expires_at) : ?>
    <p><?= $this->t('.expires_in', ['t' => $this->timeAgoInWords($this->ban->expires_at)]) ?></p>
  <?php else: ?>
    <p><?= $this->t('.permanent') ?></p>
  <?php endif ?>
</div>
