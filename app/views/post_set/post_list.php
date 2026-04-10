<div id="post-set-post-list">
  <h2>Edit Posts for Set: <?= $this->h($this->post_set->name) ?></h2>

  <p>
    Enter post IDs separated by spaces or commas. IDs not found are ignored and reported.
    Current limit: <?= (int) PostSet::post_limit() ?> posts.
  </p>

  <?= $this->formTag(['action' => 'update_posts', 'id' => $this->post_set->id], ['method' => 'post'], function () { ?>
    <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>
    <?= $this->textAreaTag('post_ids', $this->h($this->post_ids_text), ['rows' => 12, 'cols' => 90]) ?><br/>
    <?= $this->submitTag('Save Post List') ?>
    <?= $this->linkTo('Back to set', ['action' => 'show', 'id' => $this->post_set->id]) ?>
  <?php }) ?>
</div>

