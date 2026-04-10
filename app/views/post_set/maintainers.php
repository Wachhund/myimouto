<div id="post-set-maintainers">
  <h2>Maintainers for <?= $this->h($this->post_set->name) ?></h2>

  <p>
    <?= $this->linkTo('Back to set', ['action' => 'show', 'id' => $this->post_set->id]) ?>
    | <?= $this->linkTo('My maintainer invites', 'post_set_maintainer#index') ?>
  </p>

  <?php if ($this->post_set->can_edit_settings_by(current_user())) : ?>
    <h4>Invite Maintainer</h4>
    <?= $this->formTag('post_set_maintainer#create', ['method' => 'post'], function () { ?>
      <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>
      <?= $this->hiddenFieldTag('post_set_id', $this->post_set->id) ?>
      Username:
      <?= $this->textFieldTag('maintainer_name', '', ['size' => 24]) ?>
      <?= $this->submitTag('Invite') ?>
    <?php }) ?>
  <?php endif ?>

  <h4>Approved</h4>
  <?php if ($this->approved_maintainers->blank()) : ?>
    <p>No approved maintainers.</p>
  <?php else : ?>
    <table class="highlightable" width="100%">
      <thead>
        <tr>
          <th>User</th>
          <th>Status</th>
          <th>Updated</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($this->approved_maintainers as $maintainer) : ?>
          <tr class="<?= $this->cycle('even', 'odd') ?>">
            <td><?= $this->linkTo($this->h(User::find_name($maintainer->user_id)), ['user#show', 'id' => $maintainer->user_id]) ?></td>
            <td><?= $this->h($maintainer->status) ?></td>
            <td><?= $this->h($maintainer->updated_at) ?></td>
            <td>
              <?php if ($this->post_set->can_edit_settings_by(current_user())) : ?>
                <?= $this->formTag(['post_set_maintainer#revoke', 'id' => $maintainer->id], ['method' => 'post'], function () use ($maintainer) { ?>
                  <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>
                  <?= $this->submitTag('Remove') ?>
                <?php }) ?>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>

  <h4>Pending</h4>
  <?php if ($this->pending_maintainers->blank()) : ?>
    <p>No pending maintainer invites.</p>
  <?php else : ?>
    <table class="highlightable" width="100%">
      <thead>
        <tr>
          <th>User</th>
          <th>Status</th>
          <th>Updated</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($this->pending_maintainers as $maintainer) : ?>
          <tr class="<?= $this->cycle('even', 'odd') ?>">
            <td><?= $this->linkTo($this->h(User::find_name($maintainer->user_id)), ['user#show', 'id' => $maintainer->user_id]) ?></td>
            <td><?= $this->h($maintainer->status) ?></td>
            <td><?= $this->h($maintainer->updated_at) ?></td>
            <td>
              <?php if ($this->post_set->can_edit_settings_by(current_user())) : ?>
                <?= $this->formTag(['post_set_maintainer#approve', 'id' => $maintainer->id], ['method' => 'post'], function () { ?>
                  <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>
                  <?= $this->submitTag('Approve') ?>
                <?php }) ?>
              <?php endif ?>

              <?php if ($this->post_set->can_edit_settings_by(current_user()) || $maintainer->user_id == current_user()->id) : ?>
                <?= $this->formTag(['post_set_maintainer#deny', 'id' => $maintainer->id], ['method' => 'post'], function () { ?>
                  <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>
                  <?= $this->submitTag('Deny') ?>
                <?php }) ?>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>
</div>
