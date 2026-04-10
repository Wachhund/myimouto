<h4><?= $this->t('user_invites') ?></h4>
<p><?= $this->t('user_invites_text') ?></p>

<div style="margin-bottom: 2em">
  <h6><?= $this->t('user_invites2') ?></h6>
  <?= $this->formTag([], ['onsubmit' => "return confirm('" . $this->t('user_invites_text2') . "' + \$F('user_name') + '?')"], function () { ?>
    <table width="100%">
      <tfoot>
        <tr>
          <td colspan="2"><?= $this->submitTag($this->t('user_submit')) ?></td>
        </tr>
      </tfoot>
      <tbody>
        <tr>
          <td><label for="member_name"><?= $this->t('user_user') ?></label></td>
          <td>
            <?= $this->textFieldTag("member[name]", $this->params()->name, ['class' => 'ac-user-name']) ?>
          </td>
        </tr>
        <tr>
          <td><label for="member_level"><?= $this->t('user_level') ?></label></td>
          <td><?= $this->select("member", "level", ["Contributor" => CONFIG()->user_levels["Contributor"], "Privileged" => CONFIG()->user_levels["Privileged"]]) ?></td>
        </tr>
      </tbody>
    </table>
  <?php }) ?>
</div>

<div>
  <h6><?= $this->t('user_current_invites') ?></h6>
  <p><?= $this->t('user_current_invites_text') ?></p>

  <table>
    <thead>
      <tr>
        <th><?= $this->t('user_user') ?></th>
        <th><?= $this->t('users_posts') ?></th>
        <th><?= $this->t('user_fav') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($this->invited_users as $user) : ?>
        <tr>
          <td><?= $this->linkTo($this->h($user->pretty_name()), ['user#show', 'id' => $user->id]) ?></td>
          <td><?= $this->linkTo(Post::where('user_id = ' . $user->id)->count(), ['post#index', 'tags' => 'user:' . $user->name]) ?></td>
          <td><?= $this->linkTo($user->post_votes->select(['score' => 3])->size(), ['controller' => 'post', 'action' => 'index', 'tags' => 'vote:3:' . $user->name . ' order:vote']) ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>

<?= $this->partial("footer") ?>
