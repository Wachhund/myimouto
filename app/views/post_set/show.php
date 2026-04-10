<div id="post-set-show">
  <h2><?= $this->h($this->post_set->name) ?></h2>

  <p>
    <strong>Shortname:</strong> <?= $this->h($this->post_set->shortname) ?><br/>
    <strong>Creator:</strong>
    <?= $this->linkTo($this->h(User::find_name($this->post_set->creator_id)), ['user#show', 'id' => $this->post_set->creator_id]) ?><br/>
    <strong>Visibility:</strong> <?= $this->post_set->is_public ? 'public' : 'private' ?><br/>
    <strong>Post count:</strong> <?= (int) $this->post_set->post_count ?><br/>
    <strong>Created:</strong> <?= $this->h($this->post_set->created_at) ?><br/>
    <strong>Updated:</strong> <?= $this->h($this->post_set->updated_at) ?>
  </p>

  <?php if (trim((string) $this->post_set->description) !== '') : ?>
    <div style="margin-bottom: 1em;">
      <strong>Description:</strong><br/>
      <?= nl2br($this->h($this->post_set->description)) ?>
    </div>
  <?php endif ?>

  <div style="margin-bottom: 1em;">
    <?= $this->linkTo('Back to listing', ['action' => 'index']) ?>
    <?php if ($this->post_set->can_edit_settings_by(current_user())) : ?>
      | <?= $this->linkTo('Edit settings', ['action' => 'update', 'id' => $this->post_set->id]) ?>
      | <?= $this->linkTo('Maintainers', ['action' => 'maintainers', 'id' => $this->post_set->id]) ?>
    <?php endif ?>
    <?php if ($this->post_set->can_edit_posts_by(current_user())) : ?>
      | <?= $this->linkTo('Edit posts', ['action' => 'post_list', 'id' => $this->post_set->id]) ?>
    <?php endif ?>
  </div>

  <?php if (!current_user()->is_anonymous() && !$this->post_set->is_owner(current_user()) && !$this->post_set->is_maintainer(current_user()) && !$this->post_set->has_pending_invite(current_user())) : ?>
    <div style="margin-bottom: 1em;">
      <?= $this->formTag('post_set_maintainer#request_access', ['method' => 'post'], function () { ?>
        <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>
        <?= $this->hiddenFieldTag('post_set_id', $this->post_set->id) ?>
        <?= $this->submitTag('Request maintainer access') ?>
      <?php }) ?>
    </div>
  <?php endif ?>

  <?php if ($this->post_set->can_edit_posts_by(current_user())) : ?>
    <div style="margin-bottom: 1em;">
      <?= $this->formTag(['action' => 'add_post'], ['method' => 'post'], function () { ?>
        <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>
        <?= $this->hiddenFieldTag('post_set_id', $this->post_set->id) ?>
        <?= $this->textFieldTag('post_id', '', ['size' => 10, 'placeholder' => 'post id']) ?>
        <?= $this->submitTag('Add post') ?>
      <?php }) ?>
    </div>
  <?php endif ?>

  <h4>Posts in set</h4>
  <?php if (empty($this->post_ids)) : ?>
    <p>No posts assigned.</p>
  <?php else : ?>
    <ul>
      <?php foreach ($this->post_ids as $post_id) : ?>
        <li><?= $this->linkTo('#' . (int) $post_id, ['post#show', 'id' => $post_id]) ?></li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>
</div>
