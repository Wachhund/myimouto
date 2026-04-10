<h4>Username Change Requests</h4>

<?= $this->formTag(['user_name_change_request#index'], ['method' => 'get'], function () { ?>
  <fieldset style="margin-bottom: 1em;">
    <legend>Filter</legend>
    <label for="uncr_status">Status</label>
    <select id="uncr_status" name="status">
      <?php $statuses = ['', 'pending', 'approved', 'rejected', 'cancelled']; ?>
      <?php foreach ($statuses as $status) : ?>
        <option value="<?= $status ?>" <?php if ((string) $this->params()->status === $status) : ?>selected="selected"<?php endif ?>>
          <?= $status === '' ? 'all' : $status ?>
        </option>
      <?php endforeach ?>
    </select>
    <input type="submit" value="Apply" />
  </fieldset>
<?php }) ?>

<table width="100%" class="highlightable">
  <thead>
    <tr>
      <th width="12%">User</th>
      <th width="14%">Old Name</th>
      <th width="14%">Desired Name</th>
      <th width="10%">Status</th>
      <th width="12%">Created</th>
      <th width="16%">Reason</th>
      <th width="22%">Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($this->requests as $req) : ?>
      <tr class="<?= $this->cycle('even', 'odd') ?>">
        <td><?= $this->linkTo($this->h(User::find_name((int) $req->user_id)), ['controller' => 'user', 'action' => 'show', 'id' => $req->user_id]) ?></td>
        <td><?= $this->h($req->old_name) ?></td>
        <td><?= $this->h($req->desired_name) ?></td>
        <td><?= $this->h($req->status) ?></td>
        <td><?= $this->h($req->created_at) ?></td>
        <td><?= $this->h($req->reason ?: '-') ?></td>
        <td>
          <?= $this->linkTo('Details', ['user_name_change_request#show', 'id' => $req->id]) ?>
          <?php if ((string) $req->status === 'pending') : ?>
            <?= $this->formTag(['user_name_change_request#approve', 'id' => $req->id], ['method' => 'post', 'style' => 'display:inline-block; margin-left: 4px;'], function () { ?>
              <input type="hidden" name="csrf_token" value="<?= $this->h($this->csrf_token) ?>" />
              <input type="submit" value="Approve" />
            <?php }) ?>
            <?= $this->formTag(['user_name_change_request#reject', 'id' => $req->id], ['method' => 'post', 'style' => 'display:inline-block; margin-left: 4px;'], function () { ?>
              <input type="hidden" name="csrf_token" value="<?= $this->h($this->csrf_token) ?>" />
              <input type="submit" value="Reject" />
            <?php }) ?>
          <?php endif ?>
        </td>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>

<div id="paginator">
  <?= $this->willPaginate($this->requests) ?>
</div>
