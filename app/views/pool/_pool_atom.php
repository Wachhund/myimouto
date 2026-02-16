<?php if (!empty($sample)) : ?>
  <?php
    $preview_url = isset($sample->preview_url) ? $sample->preview_url : (method_exists($sample, 'preview_url') ? $sample->preview_url() : '');
    $title = method_exists($pool, 'pretty_name') ? $pool->pretty_name() : $pool->name;
  ?>
  <?= $this->linkTo($this->imageTag($preview_url, ['title' => $title]), $pool_url) ?>
<?php else : ?>
  Empty pool
<?php endif ?>
