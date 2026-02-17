<h4>Post Replacements</h4>

<?= $this->formTag(['post_replacement#index'], ['method' => 'get'], function() { ?>
  <fieldset style="margin-bottom: 1em;">
    <legend>Filter</legend>
    <label for="post_replacement_post_id">Post ID</label>
    <input
      id="post_replacement_post_id"
      name="post_id"
      type="text"
      size="8"
      value="<?= $this->h($this->params()->post_id) ?>"
    />
    <label for="post_replacement_status">Status</label>
    <select id="post_replacement_status" name="status">
      <?php $statuses = ['', 'pending', 'approved', 'rejected', 'deleted']; ?>
      <?php foreach ($statuses as $status) : ?>
        <option value="<?= $status ?>" <?php if ((string)$this->params()->status === $status) : ?>selected="selected"<?php endif ?>>
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
      <th width="5%">ID</th>
      <th width="5%">Post</th>
      <th width="10%">Status</th>
      <th width="12%">Creator</th>
      <th width="12%">Reviewer</th>
      <th width="20%">Reason</th>
      <th width="10%">Created</th>
      <?php if (current_user()->is_janitor_or_higher()) : ?>
        <th width="26%">Actions</th>
      <?php endif ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($this->post_replacements as $replacement) : ?>
      <tr>
        <td><?= (int)$replacement->id ?></td>
        <td><?= $this->linkTo('#' . (int)$replacement->post_id, ['post#show', 'id' => $replacement->post_id]) ?></td>
        <td><?= $this->h($replacement->status) ?></td>
        <td><?= $this->h(User::find_name((int)$replacement->creator_id)) ?></td>
        <td><?= $replacement->reviewed_by_id ? $this->h(User::find_name((int)$replacement->reviewed_by_id)) : '-' ?></td>
        <td><?= $this->h($replacement->reason ?: '-') ?></td>
        <td><?= $this->h($replacement->created_at) ?></td>
        <?php if (current_user()->is_janitor_or_higher()) : ?>
          <td>
            <?php if ($replacement->status === 'pending') : ?>
              <?= $this->formTag(['post_replacement#approve', 'id' => $replacement->id], ['method' => 'post', 'style' => 'display:inline-block; margin-right: 4px;'], function() use ($replacement) { ?>
                <input type="hidden" name="csrf_token" value="<?= $this->h($this->csrf_token) ?>" />
                <input type="submit" value="Approve" />
              <?php }) ?>
              <?= $this->formTag(['post_replacement#reject', 'id' => $replacement->id], ['method' => 'post', 'style' => 'display:inline-block; margin-right: 4px;'], function() use ($replacement) { ?>
                <input type="hidden" name="csrf_token" value="<?= $this->h($this->csrf_token) ?>" />
                <input type="submit" value="Reject" />
              <?php }) ?>
            <?php endif ?>
            <?= $this->formTag(['post_replacement#destroy', 'id' => $replacement->id], ['method' => 'post', 'style' => 'display:inline-block;'], function() use ($replacement) { ?>
              <input type="hidden" name="csrf_token" value="<?= $this->h($this->csrf_token) ?>" />
              <input type="submit" value="Delete" />
            <?php }) ?>
          </td>
        <?php endif ?>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>

<div id="paginator">
  <?= $this->willPaginate($this->post_replacements) ?>
</div>
