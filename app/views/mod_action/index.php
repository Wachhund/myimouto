<h4>Mod Actions</h4>

<?= $this->formTag(['mod_action#index'], ['method' => 'get'], function() { ?>
  <fieldset style="margin-bottom: 1em;">
    <legend>Filter</legend>

    <label for="mod_action_action_type">Action</label>
    <select id="mod_action_action_type" name="action_type">
      <option value="">all</option>
      <?php foreach ($this->action_types as $type) : ?>
        <option value="<?= $this->h($type) ?>" <?php if ((string)$this->params()->action_type === $type) : ?>selected="selected"<?php endif ?>>
          <?= $this->h($type) ?>
        </option>
      <?php endforeach ?>
    </select>

    <label for="mod_action_creator_name">Actor</label>
    <input
      id="mod_action_creator_name"
      name="creator_name"
      type="text"
      size="16"
      value="<?= $this->h($this->params()->creator_name) ?>"
    />

    <label for="mod_action_start_date">From</label>
    <input
      id="mod_action_start_date"
      name="start_date"
      type="date"
      value="<?= $this->h($this->params()->start_date) ?>"
    />

    <label for="mod_action_end_date">To</label>
    <input
      id="mod_action_end_date"
      name="end_date"
      type="date"
      value="<?= $this->h($this->params()->end_date) ?>"
    />

    <input type="submit" value="Apply" />
  </fieldset>
<?php }) ?>

<table width="100%" class="highlightable">
  <thead>
    <tr>
      <th width="15%">Time</th>
      <th width="12%">Actor</th>
      <th width="20%">Action</th>
      <th width="15%">Target</th>
      <th width="38%">Details</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($this->mod_actions as $ma) : ?>
      <tr class="<?= $this->cycle('even', 'odd') ?>">
        <td><?= $this->h($ma->created_at) ?></td>
        <td>
          <?= $this->linkTo(
            $this->h(User::find_name((int)$ma->creator_id)),
            ['controller' => 'user', 'action' => 'show', 'id' => $ma->creator_id]
          ) ?>
        </td>
        <td><?= $this->h($ma->action_label()) ?></td>
        <td>
          <?php $link_path = $ma->target_link_path(); ?>
          <?php $target_id = $ma->target_id(); ?>
          <?php if ($link_path && $target_id !== null) : ?>
            <?= $this->linkTo(
              $this->h($ma->target_type() . ' #' . $target_id),
              $link_path
            ) ?>
          <?php elseif ($target_id !== null) : ?>
            <?= $this->h($ma->target_type() . ' #' . $target_id) ?>
          <?php else : ?>
            -
          <?php endif ?>
        </td>
        <td>
          <?php $parsed = $ma->parsed_values(); ?>
          <?php if (!empty($parsed)) : ?>
            <?php $parts = []; ?>
            <?php foreach ($parsed as $k => $v) : ?>
              <?php $parts[] = $this->h($k) . '=' . $this->h(is_array($v) ? json_encode($v) : (string)$v); ?>
            <?php endforeach ?>
            <?= implode(', ', $parts) ?>
          <?php else : ?>
            -
          <?php endif ?>
        </td>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>

<div id="paginator">
  <?= $this->willPaginate($this->mod_actions) ?>
</div>
