<h4>Exception Logs</h4>

<?= $this->formTag(['exception_log#index'], ['method' => 'get'], function () { ?>
  <fieldset style="margin-bottom: 1em;">
    <legend>Filter</legend>

    <label for="exception_log_code">Code</label>
    <input
      id="exception_log_code"
      name="code"
      type="text"
      size="16"
      value="<?= $this->h($this->params()->code) ?>"
    />

    <label for="exception_log_exception_class">Class</label>
    <input
      id="exception_log_exception_class"
      name="exception_class"
      type="text"
      size="20"
      value="<?= $this->h($this->params()->exception_class) ?>"
    />

    <label for="exception_log_message">Message</label>
    <input
      id="exception_log_message"
      name="message"
      type="text"
      size="20"
      value="<?= $this->h($this->params()->message) ?>"
    />

    <label for="exception_log_version">Version</label>
    <input
      id="exception_log_version"
      name="version"
      type="text"
      size="10"
      value="<?= $this->h($this->params()->version) ?>"
    />

    <label for="exception_log_start_date">From</label>
    <input
      id="exception_log_start_date"
      name="start_date"
      type="date"
      value="<?= $this->h($this->params()->start_date) ?>"
    />

    <label for="exception_log_end_date">To</label>
    <input
      id="exception_log_end_date"
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
      <th width="12%">Time</th>
      <th width="12%">Code</th>
      <th width="18%">Class</th>
      <th width="30%">Message</th>
      <th width="8%">Version</th>
      <th width="10%">IP</th>
      <th width="10%">Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($this->exception_logs as $log) : ?>
      <tr class="<?= $this->cycle('even', 'odd') ?>">
        <td><?= $this->h($log->created_at) ?></td>
        <td><code><?= $this->h(substr($log->code, 0, 12)) ?>...</code></td>
        <td><?= $this->h($log->exception_class) ?></td>
        <td><?= $this->h(mb_substr($log->message, 0, 80)) ?><?php if (mb_strlen($log->message) > 80) : ?>...<?php endif ?></td>
        <td><?= $this->h($log->version) ?></td>
        <td><?= $this->h($log->ip_address) ?></td>
        <td>
          <?= $this->linkTo('Detail', ['exception_log#show', 'id' => $log->id]) ?>
        </td>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>

<div id="paginator">
  <?= $this->willPaginate($this->exception_logs) ?>
</div>

<?php if (current_user()->is_admin()) : ?>
  <div style="margin-top: 1em; padding: 0.5em; border: 1px solid #ccc;">
    <h5>Prune Old Logs</h5>
    <?= $this->formTag(['exception_log#prune'], ['method' => 'post'], function () { ?>
      <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token, ['id' => '']) ?>
      <label for="prune_days">Delete logs older than</label>
      <input id="prune_days" name="days" type="number" value="365" size="6" min="1" />
      <label>days</label>
      <?= $this->submitTag('Prune') ?>
    <?php }) ?>
  </div>
<?php endif ?>
