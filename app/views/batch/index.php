<?php $this->provide('title', $this->t('.title')) ?>
<h4>Batch Uploads</h4>

<table width="100%" class="highlightable">
  <thead>
    <tr>
      <th width="5%">#</th>
      <th width="10%"><?= $this->t('.username') ?></th>
      <th width="10%"><?= $this->t('.url') ?></th>
      <th width="25%"><?= $this->t('.tags') ?></th>
      <th width="45%"><?= $this->t('.status') ?></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($this->items as $item) : ?>
      <tr class="<?= $this->cycle('even', 'odd') ?>">
        <td><?= $item->id ?></td>
        <td><?= $this->linkTo($this->h(User::find_name($item->user_id)), ['controller' => "user", 'action' => "show", 'id' => $item->user_id]) ?></td>

        <td><?= $this->h(urldecode(pathinfo($item->url, PATHINFO_BASENAME))) ?></td>
        <td><?= $this->h($item->tags) ?></td>
        <td>
          <?php if ($item->status == "error") : ?>
            <?php if (isset($item->data->post_id)) : ?>
              <?= $this->t(['.exists_html', 'id' => $this->linkTo("#" . $item->data->post_id, ['controller' => 'post', 'action' => 'show', 'id' => $item->data->post_id])]) ?>
            <?php else: ?>
              <?= $this->h($item->data->error) ?>
            <?php endif ?>
          <?php elseif ($item->status == "pending") : ?>
            <?php if ($item->active) : ?>
              <?= $this->t('.uploading') ?>
            <?php else: ?>
              <?= $this->t('.pending') ?>
            <?php endif ?>
          <?php elseif ($item->status == "paused") : ?>
            <?= $this->t('.paused') ?>
          <?php elseif ($item->status == "finished") : ?>
            <?= $this->t(['.completed_html', 'id' => $this->linkTo("#" . $item->data->post_id, ['controller' => 'post', 'action' => 'show', 'id' => $item->data->post_id])]) ?>
          <?php endif ?>
        </td>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>

<div class="batch-buttons">
  <?= $this->formTag(['action' => "create"], ['method' => 'get'], function () { ?>
    <?= $this->submitTag($this->t('.queue_uploads'), ['name' => "queue"]) ?>
  <?php }) ?>

  <?= $this->formTag(['action' => "update"], function () { ?>
    <?= $this->hiddenFieldTag("do", "retry") ?>
    <?= $this->submitTag($this->t('.retry_failed')) ?>
  <?php }) ?>

  <?= $this->formTag(['action' => "update"], function () { ?>
    <?= $this->hiddenFieldTag("do", "clear_finished") ?>
    <?= $this->submitTag($this->t('.clear')) ?>
  <?php }) ?>

  <?= $this->formTag(['action' => "update"], function () { ?>
    <?= $this->hiddenFieldTag("do", "abort_all") ?>
    <?= $this->submitTag($this->t('.cancel')) ?>
  <?php }) ?>

  <?= $this->formTag(['action' => "update"], function () { ?>
    <?= $this->hiddenFieldTag("do", "pause") ?>
    <?= $this->submitTag($this->t('.pause')) ?>
  <?php }) ?>

  <?= $this->formTag(['action' => "update"], function () { ?>
    <?= $this->hiddenFieldTag("do", "unpause") ?>
    <?= $this->submitTag($this->t('.resume')) ?>
  <?php }) ?>
</div>

<div id="paginator">
  <?= $this->willPaginate($this->items) ?>
</div>

