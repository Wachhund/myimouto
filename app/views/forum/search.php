<div id="forum">
  <div style="margin-bottom: 1em;">
    <?= $this->formTag(['action' => "search"], ['method' => 'get'], function(){ ?>
      <?= $this->textFieldTag("query", $this->h($this->params()->query), ['size' => 40]) ?>
      <?= $this->submitTag($this->t('.search'))?>
    <?php }) ?>
  </div>

  <table class="highlightable">
    <thead>
      <tr>
        <th width="20%"><?=$this->t('.post_title') ?></th>
        <th width="50%"><?=$this->t('.message') ?></th>
        <th width="10%"><?=$this->t('.author') ?></th>
        <th width="20%"><?=$this->t('.last_updated') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($this->forum_posts as $fp) : ?>
        <tr class="<?= $this->cycle('even', 'odd') ?>">
          <td><?= $this->linkTo($this->h($fp->root()->title), ['action' => "show", 'id' => $fp->root_id()]) ?></td>
          <td><?= $this->linkTo($this->h(substr($fp->body, 0, 70)) . "...", ['action' => "show", 'id' => $fp->id]) ?></td>
          <td><?= $this->h($fp->author()) ?></td>
          <td><?= $this->t(['.last_updated_by', 't_ago' => $this->t(['time.x_ago', 't' => $this->timeAgoInWords($fp->updated_at)]), 'u' => $fp->last_updater()]) ?></td>
        <tr>
      <?php endforeach ?>
    </tbody>
  </table>

  <div id="paginator">
    <?= $this->willPaginate($this->forum_posts) ?>
  </div>
</div>
