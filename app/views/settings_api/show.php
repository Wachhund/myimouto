<?php $this->provide('title', $this->t('.title')) ?>
<h1><?= $this->t('.title') ?></h1>

<div>
  <?= $this->t('.info') ?>: <code><?= $this->h($this->user->api_key) ?></code>
</div>

<div style="margin-top: 1em;">
  <?= $this->formTag('settings_api#update', ['method' => 'put'], function(){ ?>
    <?= $this->submitTag($this->t('.reset')) ?>
  <?php }) ?>
</div>
