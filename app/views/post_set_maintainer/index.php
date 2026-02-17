<div id="post-set-maintainer-index">
  <h2>My Set Maintainer Invites</h2>

  <p><?= $this->linkTo('View all post sets', 'post_set#index') ?></p>

  <?php if ($this->maintainer_invites->blank()) : ?>
    <p>No maintainer invites or assignments.</p>
  <?php else : ?>
    <table class="highlightable" width="100%">
      <thead>
        <tr>
          <th>Set</th>
          <th>Status</th>
          <th>Updated</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($this->maintainer_invites as $invite) : ?>
          <tr class="<?= $this->cycle('even', 'odd') ?>">
            <td>
              <?php if ($invite->post_set) : ?>
                <?= $this->linkTo($this->h($invite->post_set->name), ['post_set#show', 'id' => $invite->post_set->id]) ?>
              <?php else : ?>
                (deleted set)
              <?php endif ?>
            </td>
            <td><?= $this->h($invite->status) ?></td>
            <td><?= $this->h($invite->updated_at) ?></td>
            <td>
              <?php if ($invite->status == PostSetMaintainer::STATUS_PENDING) : ?>
                <?= $this->formTag(['action' => 'deny', 'id' => $invite->id], ['method' => 'post'], function() { ?>
                  <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>
                  <?= $this->submitTag('Cancel Request') ?>
                <?php }) ?>

                <?= $this->formTag(['action' => 'block', 'id' => $invite->id], ['method' => 'post'], function() { ?>
                  <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>
                  <?= $this->submitTag('Block') ?>
                <?php }) ?>
              <?php elseif ($invite->status == PostSetMaintainer::STATUS_APPROVED) : ?>
                <?= $this->formTag(['action' => 'deny', 'id' => $invite->id], ['method' => 'post'], function() { ?>
                  <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>
                  <?= $this->submitTag('Leave set') ?>
                <?php }) ?>

                <?= $this->formTag(['action' => 'block', 'id' => $invite->id], ['method' => 'post'], function() { ?>
                  <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>
                  <?= $this->submitTag('Leave + Block') ?>
                <?php }) ?>
              <?php elseif ($invite->status == PostSetMaintainer::STATUS_BLOCKED) : ?>
                <?= $this->formTag(['action' => 'deny', 'id' => $invite->id], ['method' => 'post'], function() { ?>
                  <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>
                  <?= $this->submitTag('Unblock') ?>
                <?php }) ?>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>
</div>
