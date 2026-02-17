<div id="post-set-index">
  <h2>Post Sets</h2>

  <div style="margin-bottom: 1.5em;">
    <?= $this->formTag(['action' => 'index'], ['method' => 'get'], function() { ?>
      Name:
      <?= $this->textFieldTag('name', $this->h($this->params()->name), ['size' => 20]) ?>
      Creator:
      <?= $this->textFieldTag('creator_id', $this->h($this->params()->creator_id), ['size' => 6]) ?>
      Maintainer:
      <?= $this->textFieldTag('maintainer_id', $this->h($this->params()->maintainer_id), ['size' => 6]) ?>
      Post:
      <?= $this->textFieldTag('post_id', $this->h($this->params()->post_id), ['size' => 6]) ?>
      Visibility:
      <select name="is_public">
        <option value="" <?= $this->params()->is_public === null || $this->params()->is_public === '' ? 'selected' : '' ?>>all</option>
        <option value="1" <?= (string)$this->params()->is_public === '1' ? 'selected' : '' ?>>public</option>
        <option value="0" <?= (string)$this->params()->is_public === '0' ? 'selected' : '' ?>>private</option>
      </select>
      <?= $this->submitTag('Search', ['name' => '']) ?>
      <?php if (!current_user()->is_anonymous()) : ?>
        <?= $this->linkTo('New Set', ['action' => 'create'], ['style' => 'margin-left: 1em;']) ?>
      <?php endif ?>
    <?php }) ?>
  </div>

  <table class="highlightable" width="100%">
    <thead>
      <tr>
        <th>Name</th>
        <th>Creator</th>
        <th>Visibility</th>
        <th>Posts</th>
        <th>Updated</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($this->post_sets as $post_set) : ?>
        <tr class="<?= $this->cycle('even', 'odd') ?>">
          <td>
            <?= $this->linkTo($this->h($post_set->name), ['action' => 'show', 'id' => $post_set->id]) ?>
            <div class="desc">shortname: <?= $this->h($post_set->shortname) ?></div>
          </td>
          <td>
            <?= $this->linkTo($this->h(User::find_name($post_set->creator_id)), ['user#show', 'id' => $post_set->creator_id]) ?>
          </td>
          <td><?= $post_set->is_public ? 'public' : 'private' ?></td>
          <td><?= (int)$post_set->post_count ?></td>
          <td><?= $this->t(['time.x_ago', 't' => $this->timeAgoInWords($post_set->updated_at)]) ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>

  <div id="paginator">
    <?= $this->willPaginate($this->post_sets) ?>
  </div>
</div>

