<div id="forum">
  <div style="margin-bottom: 1em;">
    <?= $this->formTag(['action' => "search"], ['method' => 'get'], function(){ ?>
      <?= $this->textFieldTag("query", $this->h($this->params()->query), ['size' => 40]) ?>
      <?= $this->submitTag($this->t('.submit')) ?>
    <?php }) ?>
  </div>

  <table class="highlightable" style="width: 50em">
    <thead>
      <tr>
        <th width="10%"><?= $this->t('.post') ?></th>
        <th width="60%"><?= $this->t('.message') ?></th>
        <th width="15%"><?= $this->t('.author') ?></th>
        <th width="15%"><?= $this->t('.time') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($this->comments as $comment) : ?>
        <tr class="<?= $this->cycle('even', 'odd') ?>">
          <td><?= $this->linkTo("#".$comment->post_id, ['controller' => "post", 'action' => "show", 'id' => $comment->post_id]) ?></td>
          <td><?= $this->linkTo($this->h(substr($comment->body, 0, 70)) . "...", ['controller' => "post", 'action' => "show", 'id' => $comment->post_id, 'anchor' => "c#".$comment->id]) ?></td>
          <td>
            <?php if ($comment->user_id) : ?>
              <a href="/user/show/<?= $comment->user_id ?>"><?= $this->h($comment->pretty_author()) ?></a>
            <?php else: ?>
              <?= $this->h($comment->pretty_author()) ?>
            <?php endif ?>
          </td>

          <td><?= $this->t(['time.x_ago', 't' => $this->timeAgoInWords($comment->created_at)]) ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>

  <div id="paginator">
    <?= $this->willPaginate($this->comments) ?>
  </div>
</div>

<?= $this->partial("footer") ?>
