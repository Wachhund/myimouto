<h4>Username Change Request #<?= (int)$this->change_request->id ?></h4>

<table class="form" width="50%">
  <tr>
    <th width="30%">User</th>
    <td><?= $this->linkTo($this->h(User::find_name((int)$this->change_request->user_id)), ['controller' => 'user', 'action' => 'show', 'id' => $this->change_request->user_id]) ?></td>
  </tr>
  <tr>
    <th>Old Name</th>
    <td><?= $this->h($this->change_request->old_name) ?></td>
  </tr>
  <tr>
    <th>Desired Name</th>
    <td><?= $this->h($this->change_request->desired_name) ?></td>
  </tr>
  <tr>
    <th>Reason</th>
    <td><?= $this->h($this->change_request->reason ?: '-') ?></td>
  </tr>
  <tr>
    <th>Status</th>
    <td><?= $this->h($this->change_request->status) ?></td>
  </tr>
  <tr>
    <th>Created</th>
    <td><?= $this->h($this->change_request->created_at) ?></td>
  </tr>
  <?php if ($this->change_request->staff_id) : ?>
    <tr>
      <th>Reviewed By</th>
      <td><?= $this->linkTo($this->h(User::find_name((int)$this->change_request->staff_id)), ['controller' => 'user', 'action' => 'show', 'id' => $this->change_request->staff_id]) ?></td>
    </tr>
  <?php endif ?>
  <?php if ($this->change_request->staff_reason) : ?>
    <tr>
      <th>Staff Reason</th>
      <td><?= $this->h($this->change_request->staff_reason) ?></td>
    </tr>
  <?php endif ?>
  <?php if ($this->change_request->resolved_at) : ?>
    <tr>
      <th>Resolved</th>
      <td><?= $this->h($this->change_request->resolved_at) ?></td>
    </tr>
  <?php endif ?>
</table>

<?php if ((string)$this->change_request->status === 'pending') : ?>
  <div style="margin-top: 1em;">
    <?php if (current_user()->is_mod_or_higher()) : ?>
      <?= $this->formTag(['user_name_change_request#approve', 'id' => $this->change_request->id], ['method' => 'post', 'style' => 'display:inline-block; margin-right: 8px;'], function() { ?>
        <input type="hidden" name="csrf_token" value="<?= $this->h($this->csrf_token) ?>" />
        <input type="submit" value="Approve" />
      <?php }) ?>

      <?= $this->formTag(['user_name_change_request#reject', 'id' => $this->change_request->id], ['method' => 'post', 'style' => 'display:inline-block; margin-right: 8px;'], function() { ?>
        <input type="hidden" name="csrf_token" value="<?= $this->h($this->csrf_token) ?>" />
        <label for="staff_reason">Reason:</label>
        <input type="text" id="staff_reason" name="staff_reason" size="40" />
        <input type="submit" value="Reject" />
      <?php }) ?>
    <?php endif ?>

    <?php if ((int)$this->change_request->user_id === (int)current_user()->id) : ?>
      <?= $this->formTag(['user_name_change_request#cancel', 'id' => $this->change_request->id], ['method' => 'post', 'style' => 'display:inline-block;'], function() { ?>
        <input type="hidden" name="csrf_token" value="<?= $this->h($this->csrf_token) ?>" />
        <input type="submit" value="Cancel Request" onclick="return confirm('Are you sure you want to cancel this request?');" />
      <?php }) ?>
    <?php endif ?>
  </div>
<?php endif ?>
