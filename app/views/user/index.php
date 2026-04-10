<h2><?= $this->t('users') ?></h2>

<?= $this->formTag(['action' => 'index'], ['method' => 'get'], function () { ?>
  <table>
    <tfoot>
      <tr>
        <td colspan="2"><?= $this->submitTag($this->t('users_search')) ?></td>
      </tr>
    </tfoot>
    <tbody>
      <tr>
        <th><?= $this->t('users_name') ?></th>
        <td><?= $this->textFieldTag("name", $this->params()->name) ?></td>
      </tr>
      <tr>
        <th><?= $this->t('users_level') ?></th>
        <td><?= $this->selectTag("level", [array_merge(["Any" => "any"], CONFIG()->user_levels), $this->params()->level]) ?></td>
      </tr>
      <tr>
        <th><?= $this->t('users_order') ?></th>
        <td><?= $this->selectTag("order", [["Name" => "name", "Posts" => "posts", "Notes" => "notes", "Date" => "date"], $this->params()->order]) ?></td>
      </tr>
    </tbody>
  </table>
<?php }) ?>

<table>
  <thead>
    <tr>
      <th><?= $this->t('users_name') ?></th>
      <th><?= $this->t('users_posts') ?></th>
      <th><?= $this->t('users_deleted') ?></th>
      <th><?= $this->t('users_pos') ?></th>
      <th><?= $this->t('users_neg') ?></th>
      <th><?= $this->t('users_notes') ?></th>
      <th><?= $this->t('users_level') ?></th>
      <th><?= $this->t('users_joined') ?></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($this->users as $user) : ?>
      <tr class="<?= $this->cycle('even', 'odd') ?>">
        <td>
          <?= $this->linkTo($this->h($user->pretty_name()), ['action' => 'show', 'id' => $user->id]) ?>
          <?php if ($user->invited_by) : ?>
            &larr; <?= $this->linkTo($this->h($user->invited_by_name()), ['action' => 'show', 'id' => $user->invited_by]) ?>
          <?php endif ?>
        </td>
        <td><?= $this->linkTo($user->post_count(), ['post#index', 'tags' => 'user:' . $user->name]) ?></td>
        <td><?= Post::where('user_id =' . $user->id . " and status = 'deleted'")->count() ?></td>
        <?php if ($user->post_count() > 100) : ?>
          <td><?= round(100 * Post::where("user_id = ? and status = 'active' and score > 1", $user->id)->count() / $user->post_count()) ?>%</td>
          <td><?= round(100 * Post::where("user_id = ? and status = 'active' and score < -1", $user->id)->count() / $user->post_count()) ?>%</td>
        <?php else: ?>
          <td></td>
          <td></td>
        <?php endif ?>
        <td><?= $this->linkTo(NoteVersion::where('user_id = ' . $user->id)->count(), ['note#history', 'user_id' => $user->id]) ?></td>
        <td><?= $user->pretty_level() ?></td>
        <td><span title="<?= $user->created_at ?>"><?= $this->t(['time.x_ago', 't' => $this->timeAgoInWords($user->created_at)]) ?></span></td>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>

<div id="paginator">
  <?= $this->willPaginate($this->users) ?>
</div>

<?= $this->partial("footer") ?>
