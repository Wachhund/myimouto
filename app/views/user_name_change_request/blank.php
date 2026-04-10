<h4>Request Username Change</h4>

<?php if (!$this->eligibility['allowed']) : ?>
  <p><?= $this->h($this->eligibility['reason']) ?></p>
<?php else : ?>
  <p>Your current username is <strong><?= $this->h(current_user()->name) ?></strong>.</p>

  <?= $this->formTag(['user_name_change_request#create'], ['method' => 'post'], function () { ?>
    <input type="hidden" name="csrf_token" value="<?= $this->h($this->csrf_token) ?>" />
    <table class="form">
      <tr>
        <th><label for="desired_name">Desired Username</label></th>
        <td>
          <input type="text" id="desired_name" name="desired_name" size="30" maxlength="20" value="<?= $this->h($this->change_request->desired_name) ?>" />
          <p class="hint">2-20 characters. No spaces, semicolons, or commas.</p>
        </td>
      </tr>
      <tr>
        <th><label for="reason">Reason (optional)</label></th>
        <td>
          <textarea id="reason" name="reason" rows="4" cols="50"><?= $this->h($this->change_request->reason) ?></textarea>
        </td>
      </tr>
      <tr>
        <td colspan="2">
          <input type="submit" value="Submit Request" />
        </td>
      </tr>
    </table>
  <?php }) ?>
<?php endif ?>
