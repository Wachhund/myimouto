<h2>Delete My Account</h2>

<div style="margin-bottom: 1em; padding: 1em; border: 2px solid #c00; background: #fff0f0;">
  <p><strong>Warning:</strong> This action is irreversible.</p>
  <p>Your account will be anonymized. Your username, email, and password will be permanently removed. Your posts, comments, and other contributions will remain but will no longer be linked to your identity.</p>
  <p>The following data will be permanently deleted:</p>
  <ul>
    <li>Your votes</li>
    <li>Your favorites</li>
    <li>Your tag subscriptions</li>
  </ul>
</div>

<?= $this->formTag(['action' => 'execute_delete_account'], function(){ ?>
  <table class="form">
    <tbody>
      <tr>
        <th><label for="password">Current Password</label></th>
        <td><?= $this->passwordFieldTag("password", "", ['id' => 'password']) ?></td>
      </tr>
      <tr>
        <th><label for="confirm_deletion">Confirm</label></th>
        <td>
          <?= $this->checkBoxTag("confirm_deletion", "1", false) ?>
          <label for="confirm_deletion">I understand this action is irreversible and I want to delete my account</label>
        </td>
      </tr>
    </tbody>
    <tfoot>
      <tr>
        <td></td>
        <td>
          <?= $this->submitTag("Delete My Account") ?>
          <?= $this->buttonToFunction("Cancel", "history.back()") ?>
        </td>
      </tr>
    </tfoot>
  </table>
<?php }) ?>

<?= $this->partial("footer") ?>
