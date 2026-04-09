<div id="takedown-index">
  <h4>Takedowns</h4>

  <?= $this->formTag(['takedown#index'], ['method' => 'get'], function() { ?>
    <fieldset style="margin-bottom: 1em;">
      <legend>Filter</legend>

      <label for="takedown_status">Status</label>
      <select id="takedown_status" name="status">
        <option value="">all</option>
        <?php foreach (Takedown::VALID_STATUSES as $status) : ?>
          <option value="<?= $this->h($status) ?>" <?php if ((string)$this->params()->status === $status) : ?>selected="selected"<?php endif ?>>
            <?= $this->h($status) ?>
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
        <th width="10%">Status</th>
        <th width="15%">Email</th>
        <th width="10%">Creator</th>
        <th width="8%">Posts</th>
        <th width="25%">Reason</th>
        <th width="12%">Created</th>
        <th width="10%">Approver</th>
        <th width="5%"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($this->takedowns as $takedown) : ?>
        <tr class="<?= $this->cycle('even', 'odd') ?>">
          <td><?= $this->linkTo('#' . (int)$takedown->id, ['action' => 'show', 'id' => $takedown->id]) ?></td>
          <td><?= $this->h($takedown->status_label()) ?></td>
          <td><?= $this->h($takedown->email) ?></td>
          <td>
            <?php if ($takedown->creator_id) : ?>
              <?php try { ?>
                <?= $this->linkTo($this->h(User::find_name((int)$takedown->creator_id)), ['user#show', 'id' => $takedown->creator_id]) ?>
              <?php } catch (Exception $e) { ?>
                User #<?= (int)$takedown->creator_id ?>
              <?php } ?>
            <?php else : ?>
              -
            <?php endif ?>
          </td>
          <td><?= (int)$takedown->post_count() ?></td>
          <td><?= $this->h(mb_strimwidth((string)$takedown->reason, 0, 80, '...')) ?></td>
          <td><?= $this->h($takedown->created_at) ?></td>
          <td>
            <?php if ($takedown->approver_id) : ?>
              <?php try { ?>
                <?= $this->linkTo($this->h(User::find_name((int)$takedown->approver_id)), ['user#show', 'id' => $takedown->approver_id]) ?>
              <?php } catch (Exception $e) { ?>
                User #<?= (int)$takedown->approver_id ?>
              <?php } ?>
            <?php else : ?>
              -
            <?php endif ?>
          </td>
          <td><?= $this->linkTo('View', ['action' => 'show', 'id' => $takedown->id]) ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>

  <?php if (!$this->takedowns->size()) : ?>
    <p>No takedowns found.</p>
  <?php endif ?>

  <div id="paginator">
    <?= $this->willPaginate($this->takedowns) ?>
  </div>

  <div style="margin-top: 1em;">
    <?= $this->linkTo('Create Takedown', ['action' => 'create']) ?>
  </div>
</div>
