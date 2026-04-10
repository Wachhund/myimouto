<div id="takedown-show">
  <h4>Takedown #<?= (int) $this->takedown->id ?></h4>

  <table width="100%">
    <tbody>
      <tr>
        <th width="15%">Status</th>
        <td><?= $this->h($this->takedown->status_label()) ?></td>
      </tr>
      <tr>
        <th>Vericode</th>
        <td><code><?= $this->h($this->takedown->vericode) ?></code></td>
      </tr>
      <?php if ($this->takedown->email) : ?>
        <tr>
          <th>Email</th>
          <td><?= $this->h($this->takedown->email) ?></td>
        </tr>
      <?php endif ?>
      <?php if ($this->takedown->source) : ?>
        <tr>
          <th>Source</th>
          <td><?= nl2br($this->h($this->takedown->source)) ?></td>
        </tr>
      <?php endif ?>
      <tr>
        <th>Reason</th>
        <td><?= nl2br($this->h($this->takedown->reason)) ?></td>
      </tr>
      <?php if ($this->takedown->instructions) : ?>
        <tr>
          <th>Instructions</th>
          <td><?= nl2br($this->h($this->takedown->instructions)) ?></td>
        </tr>
      <?php endif ?>
      <?php if ($this->takedown->notes) : ?>
        <tr>
          <th>Staff Notes</th>
          <td><?= nl2br($this->h($this->takedown->notes)) ?></td>
        </tr>
      <?php endif ?>
      <tr>
        <th>Creator</th>
        <td>
          <?php if ($this->takedown->creator_id) : ?>
            <?php try { ?>
              <?= $this->linkTo($this->h(User::find_name((int) $this->takedown->creator_id)), ['user#show', 'id' => $this->takedown->creator_id]) ?>
            <?php } catch (Exception $e) { ?>
              User #<?= (int) $this->takedown->creator_id ?>
            <?php } ?>
          <?php else : ?>
            -
          <?php endif ?>
        </td>
      </tr>
      <?php if ($this->takedown->approver_id) : ?>
        <tr>
          <th>Approver</th>
          <td>
            <?php try { ?>
              <?= $this->linkTo($this->h(User::find_name((int) $this->takedown->approver_id)), ['user#show', 'id' => $this->takedown->approver_id]) ?>
            <?php } catch (Exception $e) { ?>
              User #<?= (int) $this->takedown->approver_id ?>
            <?php } ?>
          </td>
        </tr>
      <?php endif ?>
      <tr>
        <th>Created</th>
        <td><?= $this->h($this->takedown->created_at) ?></td>
      </tr>
      <?php if ($this->takedown->updated_at) : ?>
        <tr>
          <th>Updated</th>
          <td><?= $this->h($this->takedown->updated_at) ?></td>
        </tr>
      <?php endif ?>
    </tbody>
  </table>

  <!-- Posts linked to this takedown -->
  <h5 style="margin-top: 1.5em;">Posts (<?= count($this->takedown_posts) ?>)</h5>

  <?php if (count($this->takedown_posts)) : ?>
    <table width="100%" class="highlightable">
      <thead>
        <tr>
          <th width="15%">Post ID</th>
          <th width="20%">Status</th>
          <th width="25%">Added</th>
          <th width="20%"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($this->takedown_posts as $tp) : ?>
          <tr class="<?= $this->cycle('even', 'odd') ?>">
            <td><?= $this->linkTo('#' . (int) $tp->post_id, ['post#show', 'id' => $tp->post_id]) ?></td>
            <td><?= $this->h($tp->status) ?></td>
            <td><?= $this->h($tp->created_at) ?></td>
            <td>
              <?= $this->formTag(['action' => 'remove_posts', 'id' => $this->takedown->id], ['method' => 'post', 'style' => 'display:inline;'], function () use ($tp) { ?>
                <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>
                <?= $this->hiddenFieldTag('post_ids', (int) $tp->post_id) ?>
                <?= $this->submitTag('Remove', ['onclick' => "return confirm('Remove this post from takedown?')"]) ?>
              <?php }) ?>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php else : ?>
    <p>No posts linked to this takedown.</p>
  <?php endif ?>

  <!-- Add posts form -->
  <div style="margin-top: 1em;">
    <?= $this->formTag(['action' => 'add_posts', 'id' => $this->takedown->id], ['method' => 'post'], function () { ?>
      <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>
      <label>Add Post IDs (space or comma separated):</label><br/>
      <?= $this->textFieldTag('post_ids', '', ['size' => 40, 'placeholder' => 'e.g. 123 456 789']) ?>
      <?= $this->submitTag('Add Posts') ?>
    <?php }) ?>
  </div>

  <!-- Process takedown form -->
  <?php if (in_array((string) $this->takedown->status, [Takedown::STATUS_PENDING, Takedown::STATUS_PARTIAL], true)) : ?>
    <div style="margin-top: 1.5em;">
      <h5>Process Takedown</h5>
      <?= $this->formTag(['action' => 'update', 'id' => $this->takedown->id], ['method' => 'post'], function () { ?>
        <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>

        <table>
          <tr>
            <td><label>Instructions (visible to requester)</label></td>
            <td><?= $this->textAreaTag('takedown[instructions]', $this->h($this->takedown->instructions), ['rows' => 4, 'cols' => 60]) ?></td>
          </tr>
          <tr>
            <td><label>Staff Notes (internal)</label></td>
            <td><?= $this->textAreaTag('takedown[notes]', $this->h($this->takedown->notes), ['rows' => 4, 'cols' => 60]) ?></td>
          </tr>
          <tr>
            <td><label>Decision</label></td>
            <td>
              <select name="takedown[status]">
                <option value="">-- Keep current --</option>
                <option value="approved">Approve</option>
                <option value="denied">Deny</option>
                <option value="partial">Partial</option>
              </select>
            </td>
          </tr>
          <tr>
            <td colspan="2"><?= $this->submitTag('Update Takedown') ?></td>
          </tr>
        </table>
      <?php }) ?>
    </div>
  <?php endif ?>

  <?php if (current_user()->is_admin()) : ?>
    <div style="margin-top: 1em;">
      <?= $this->formTag(['action' => 'destroy', 'id' => $this->takedown->id], ['method' => 'post', 'style' => 'display:inline;'], function () { ?>
        <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token) ?>
        <?= $this->submitTag('Delete Takedown', ['onclick' => "return confirm('Permanently delete this takedown?')"]) ?>
      <?php }) ?>
    </div>
  <?php endif ?>

  <div style="margin-top: 1em;">
    <?= $this->linkTo('Back to takedowns', ['action' => 'index']) ?>
  </div>
</div>
