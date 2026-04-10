<h2>Delete User: <?= $this->h($this->user->pretty_name()) ?></h2>

<div style="margin-bottom: 1em; padding: 1em; border: 2px solid #c00; background: #fff0f0;">
  <p><strong>Warning:</strong> This action will anonymize the user account. This cannot be undone.</p>
  <p>The user's name will be replaced with <code>user_<?= $this->user->id ?></code>, their password and email will be cleared, and their level will be set to Blocked.</p>
</div>

<table width="50%">
  <tr>
    <td width="40%"><strong>Username</strong></td>
    <td width="60%"><?= $this->h($this->user->pretty_name()) ?></td>
  </tr>
  <tr>
    <td><strong>User ID</strong></td>
    <td><?= $this->user->id ?></td>
  </tr>
  <tr>
    <td><strong>Level</strong></td>
    <td><?= $this->user->pretty_level() ?></td>
  </tr>
  <tr>
    <td><strong>Joined</strong></td>
    <td><?= substr($this->user->created_at, 0, 10) ?></td>
  </tr>
</table>

<h4>Affected Records</h4>
<table width="50%">
  <tr>
    <td width="40%"><strong>Votes</strong></td>
    <td width="60%"><?= $this->impact['post_votes'] ?></td>
  </tr>
  <tr>
    <td><strong>Favorites</strong></td>
    <td><?= $this->impact['favorites'] ?></td>
  </tr>
  <tr>
    <td><strong>Tag Subscriptions</strong></td>
    <td><?= $this->impact['tag_subscriptions'] ?></td>
  </tr>
</table>

<?= $this->formTag(['controller' => 'user_deletion', 'action' => 'execute', 'id' => $this->user->id], function () { ?>
  <table class="form" style="margin-top: 1em;">
    <tbody>
      <tr>
        <th><label for="reason">Reason</label></th>
        <td><?= $this->textAreaTag("reason", "", ['size' => '40x3', 'id' => 'reason']) ?></td>
      </tr>
      <tr>
        <th><label for="confirm_deletion">Confirm</label></th>
        <td>
          <?= $this->checkBoxTag("confirm_deletion", "1", false) ?>
          <label for="confirm_deletion">I understand this action is irreversible</label>
        </td>
      </tr>
    </tbody>
    <tfoot>
      <tr>
        <td></td>
        <td>
          <?= $this->submitTag("Delete User") ?>
          <?= $this->buttonToFunction("Cancel", "history.back()") ?>
        </td>
      </tr>
    </tfoot>
  </table>
<?php }) ?>
